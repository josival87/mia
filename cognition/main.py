from __future__ import annotations

import base64
import json
import logging
import re
import unicodedata
from datetime import date, timedelta
from typing import Any, Literal

import httpx
from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from pydantic import BaseModel, Field

app = FastAPI(title="Mia Cognition", version="1.0.0")
logger = logging.getLogger("mia.cognition")

GEMINI_INLINE_AUDIO_MAX_BYTES = 14 * 1024 * 1024


class Category(BaseModel):
    id: int
    name: str
    kind: Literal["income", "expense", "task"]


class ParseRequest(BaseModel):
    text: str = Field(min_length=1, max_length=5000)
    hint: Literal["auto", "finance", "task"] = "auto"
    categories: list[Category] = Field(default_factory=list)
    primary_provider: Literal["gemini", "openai"] = "gemini"
    openai_api_key: str | None = None
    openai_model: str = "gpt-5-mini"
    gemini_api_key: str | None = None
    gemini_model: str = "gemini-3.6-flash"


RECORD_SCHEMA: dict[str, Any] = {
    "type": "object",
    "additionalProperties": False,
    "properties": {
        "kind": {"type": "string", "enum": ["finance", "task"]},
        "confidence": {"type": "number", "minimum": 0, "maximum": 1},
        "data": {
            "type": "object",
            "additionalProperties": False,
            "properties": {
                "type": {"type": ["string", "null"], "enum": ["income", "expense", None]},
                "description": {"type": ["string", "null"]},
                "amount": {"type": ["number", "null"]},
                "occurred_on": {"type": ["string", "null"]},
                "name": {"type": ["string", "null"]},
                "priority": {"type": ["string", "null"], "enum": ["low", "medium", "high", None]},
                "status": {"type": ["string", "null"], "enum": ["todo", "doing", "done", None]},
                "due_on": {"type": ["string", "null"]},
                "category_id": {"type": ["integer", "null"]},
            },
            "required": ["type", "description", "amount", "occurred_on", "name", "priority", "status", "due_on", "category_id"],
        },
    },
    "required": ["kind", "confidence", "data"],
}


def normalize(value: str) -> str:
    return "".join(char for char in unicodedata.normalize("NFD", value.lower()) if unicodedata.category(char) != "Mn")


def match_category(text: str, categories: list[Category], kind: str) -> int | None:
    normalized = normalize(text)
    candidates = [category for category in categories if category.kind == kind]
    for category in sorted(candidates, key=lambda item: len(item.name), reverse=True):
        if normalize(category.name) in normalized:
            return category.id
    aliases = {
        "alimentacao": ["almoco", "jantar", "cafe", "mercado", "restaurante", "lanche"],
        "transporte": ["uber", "combustivel", "gasolina", "onibus", "metro"],
        "moradia": ["aluguel", "condominio", "energia", "luz", "agua"],
        "saude": ["farmacia", "medico", "consulta", "academia"],
        "trabalho": ["cliente", "reuniao", "relatorio", "proposta"],
        "estudos": ["curso", "prova", "estudar", "aula"],
    }
    for category in candidates:
        if any(word in normalized for word in aliases.get(normalize(category.name), [])):
            return category.id
    return None


def extract_amount(text: str) -> float | None:
    patterns = [r"r\$\s*([\d\.]+(?:,\d{1,2})?)", r"([\d\.]+(?:,\d{1,2})?)\s*(?:reais|real)\b"]
    for pattern in patterns:
        found = re.search(pattern, text, re.IGNORECASE)
        if found:
            try:
                return float(found.group(1).replace(".", "").replace(",", "."))
            except ValueError:
                pass
    return None


def local_parse(request: ParseRequest) -> dict[str, Any]:
    text = request.text.strip()
    normalized = normalize(text)
    amount = extract_amount(text)
    task_terms = ("lembr", "preciso", "tarefa", "atividade", "agendar", "reuniao", "enviar", "fazer", "comprar")
    finance_terms = ("paguei", "gastei", "recebi", "ganhei", "entrada", "saida", "reais", "r$")
    is_task = request.hint == "task" or (request.hint == "auto" and any(term in normalized for term in task_terms) and not any(term in normalized for term in finance_terms))

    if not is_task:
        income_terms = ("recebi", "ganhei", "salario", "vendi", "entrada", "freelance", "rendimento")
        record_type = "income" if any(term in normalized for term in income_terms) else "expense"
        category_id = match_category(text, request.categories, record_type)
        description = re.sub(r"\s+", " ", re.sub(r"\b(?:r\$\s*)?[\d\.]+(?:,\d{1,2})?\s*(?:reais|real)?\b", "", text, flags=re.IGNORECASE)).strip(" .,-")
        return {
            "kind": "finance",
            "confidence": 0.82 if amount else 0.55,
            "provider": "local",
            "data": {
                "type": record_type,
                "description": description.capitalize() or "Lançamento via Telegram",
                "amount": amount or 0,
                "occurred_on": date.today().isoformat(),
                "category_id": category_id,
            },
        }

    priority = "high" if any(word in normalized for word in ("urgente", "prioridade alta", "importante")) else "low" if "prioridade baixa" in normalized else "medium"
    status = "doing" if any(word in normalized for word in ("em andamento", "em execucao", "comecei")) else "done" if any(word in normalized for word in ("conclui", "finalizei", "feito")) else "todo"
    due_on = (date.today() + timedelta(days=1)).isoformat() if "amanha" in normalized else date.today().isoformat() if "hoje" in normalized else None
    name = re.sub(r"^(?:me lembre de|lembrar de|preciso|tarefa|atividade|agendar)\s+", "", text, flags=re.IGNORECASE).strip(" .")
    return {
        "kind": "task",
        "confidence": 0.72,
        "provider": "local",
        "data": {
            "name": name.capitalize() or "Atividade via Telegram",
            "description": text,
            "priority": priority,
            "status": status,
            "due_on": due_on,
            "category_id": match_category(text, request.categories, "task"),
        },
    }


def prompt_for(request: ParseRequest) -> str:
    category_text = ", ".join(f"{c.id}:{c.name}({c.kind})" for c in request.categories) or "nenhuma"
    return (
        "Interprete a mensagem em português do Brasil como um único lançamento financeiro ou atividade. "
        "Use apenas category_id listado e nunca invente IDs. Valores são sempre positivos; o campo type define entrada ou saída. "
        "Datas devem ser YYYY-MM-DD. Se a mensagem não disser uma data, use a data de hoje. "
        f"Data de hoje: {date.today().isoformat()}. Dica de tipo: {request.hint}. Categorias: {category_text}.\n\n"
        f"Mensagem: {request.text}"
    )


async def parse_with_openai(request: ParseRequest) -> dict[str, Any]:
    payload = {
        "model": request.openai_model,
        "instructions": "Você extrai dados estruturados para a assistente financeira Mia. Responda estritamente conforme o schema.",
        "input": prompt_for(request),
        "text": {"format": {"type": "json_schema", "name": "mia_record", "strict": True, "schema": RECORD_SCHEMA}},
        "store": False,
    }
    async with httpx.AsyncClient(timeout=45) as client:
        response = await client.post("https://api.openai.com/v1/responses", headers={"Authorization": f"Bearer {request.openai_api_key}"}, json=payload)
        response.raise_for_status()
        body = response.json()
    output_text = body.get("output_text")
    if not output_text:
        for item in body.get("output", []):
            for content in item.get("content", []):
                if content.get("type") == "output_text":
                    output_text = content.get("text")
                    break
    if not output_text:
        raise ValueError("OpenAI response did not contain output_text")
    result = json.loads(output_text)
    result["provider"] = "openai"
    return result


async def parse_with_gemini(request: ParseRequest) -> dict[str, Any]:
    return await parse_parts_with_gemini(request, [{"text": prompt_for(request)}])


async def parse_parts_with_gemini(request: ParseRequest, parts: list[dict[str, Any]]) -> dict[str, Any]:
    url = f"https://generativelanguage.googleapis.com/v1beta/models/{request.gemini_model}:generateContent"
    payload = {
        "contents": [{"parts": parts}],
        "generationConfig": {
            "responseMimeType": "application/json",
            "responseJsonSchema": RECORD_SCHEMA,
        },
    }
    async with httpx.AsyncClient(timeout=45) as client:
        response = await client.post(url, headers={"x-goog-api-key": request.gemini_api_key or ""}, json=payload)
        if response.is_error:
            safe_error = response.text.replace(request.gemini_api_key or "", "[redacted]")[:800]
            logger.warning("Gemini API returned HTTP %s: %s", response.status_code, safe_error)
        response.raise_for_status()
        body = response.json()
    result = json.loads(body["candidates"][0]["content"]["parts"][0]["text"])
    result["provider"] = "gemini"
    return result


async def parse_request(request: ParseRequest) -> dict[str, Any]:
    providers = [request.primary_provider, "openai" if request.primary_provider == "gemini" else "gemini"]
    for provider in providers:
        if provider == "gemini" and request.gemini_api_key:
            try:
                return await parse_with_gemini(request)
            except Exception as exc:
                logger.warning("Gemini text parsing failed: %s", exc)
        if provider == "openai" and request.openai_api_key:
            try:
                return await parse_with_openai(request)
            except Exception as exc:
                logger.warning("OpenAI text parsing failed: %s", exc)
    return local_parse(request)


async def parse_audio_with_gemini(request: ParseRequest, audio: bytes, mime_type: str) -> dict[str, Any]:
    prompt = (
        "O conteúdo a interpretar está no áudio anexado. Compreenda a fala em português do Brasil e extraia "
        "um único lançamento financeiro ou atividade, seguindo todas as regras e categorias deste contexto.\n\n"
        + prompt_for(request)
    )
    result = await parse_parts_with_gemini(request, [
        {"text": prompt},
        {"inlineData": {"mimeType": mime_type, "data": base64.b64encode(audio).decode("ascii")}},
    ])
    result["audio_provider"] = "gemini"
    return result


async def transcribe_with_openai(api_key: str, audio: bytes, filename: str, mime_type: str) -> str:
    async with httpx.AsyncClient(timeout=90) as client:
        response = await client.post(
            "https://api.openai.com/v1/audio/transcriptions",
            headers={"Authorization": f"Bearer {api_key}"},
            data={"model": "gpt-transcribe", "language": "pt", "response_format": "json"},
            files={"file": (filename, audio, mime_type)},
        )
        response.raise_for_status()
        transcript = response.json().get("text", "")
    if not transcript:
        raise ValueError("OpenAI transcription did not contain text")
    return transcript


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/parse")
async def parse(request: ParseRequest) -> dict[str, Any]:
    return await parse_request(request)


@app.post("/parse-audio")
async def parse_audio(
    file: UploadFile = File(...),
    categories: str = Form("[]"),
    context: str = Form("Mensagem recebida por áudio."),
    primary_provider: Literal["gemini", "openai"] = Form("gemini"),
    openai_api_key: str | None = Form(None),
    openai_model: str = Form("gpt-5-mini"),
    gemini_api_key: str | None = Form(None),
    gemini_model: str = Form("gemini-3.6-flash"),
) -> dict[str, Any]:
    audio = await file.read()
    if len(audio) > 25 * 1024 * 1024:
        raise HTTPException(413, "Áudio maior que 25 MB.")

    try:
        parsed_categories = json.loads(categories)
    except json.JSONDecodeError as exc:
        raise HTTPException(422, "Categorias inválidas.") from exc

    request = ParseRequest(
        text=context,
        categories=parsed_categories,
        primary_provider=primary_provider,
        openai_api_key=openai_api_key,
        openai_model=openai_model,
        gemini_api_key=gemini_api_key,
        gemini_model=gemini_model,
    )

    configured = bool(gemini_api_key or openai_api_key)
    gemini_too_large = False
    providers = [primary_provider, "openai" if primary_provider == "gemini" else "gemini"]
    for provider in providers:
        if provider == "gemini" and gemini_api_key:
            if len(audio) > GEMINI_INLINE_AUDIO_MAX_BYTES:
                gemini_too_large = True
                continue
            try:
                return await parse_audio_with_gemini(request, audio, file.content_type or "audio/ogg")
            except Exception as exc:
                logger.warning("Gemini audio parsing failed: %s", exc)
        if provider == "openai" and openai_api_key:
            try:
                transcript = await transcribe_with_openai(
                    openai_api_key,
                    audio,
                    file.filename or "audio.ogg",
                    file.content_type or "audio/ogg",
                )
                text_request = request.model_copy(update={"text": transcript, "primary_provider": "openai"})
                result = await parse_request(text_request)
                result["transcript"] = transcript
                result["audio_provider"] = "openai"
                return result
            except Exception as exc:
                logger.warning("OpenAI audio parsing failed: %s", exc)

    if gemini_too_large and not openai_api_key:
        raise HTTPException(413, "Para o Gemini, o áudio deve ter até 14 MB neste canal.")
    if not configured:
        raise HTTPException(422, "Configure uma chave Gemini ou OpenAI para interpretar áudio.")
    raise HTTPException(502, "Os provedores de IA configurados não conseguiram interpretar o áudio.")
