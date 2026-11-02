package main

// Roteador de IA de custo zero.
// Ordem padrão: parser local → LLM local (llamafile/llama.cpp) → Groq → Cerebras → Gemini (só nível pago) → OpenAI.
// As chaves vivem apenas no ambiente deste serviço; o Laravel não envia segredos.

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"strconv"
	"strings"
	"sync"
	"time"
)

const maxProviderResponseBytes = 2 * 1024 * 1024

var errQuotaExceeded = errors.New("cota do provedor esgotada")

// Usage é devolvido ao Laravel para medir custo e cota por usuário.
type Usage struct {
	Provider     string `json:"provider"`
	Model        string `json:"model"`
	InputTokens  int    `json:"input_tokens"`
	OutputTokens int    `json:"output_tokens"`
}

type aiProvider struct {
	Name       string
	Kind       string // "openai-compatible", "gemini", "openai"
	BaseURL    string
	APIKey     string
	Model      string
	DailyLimit int // 0 = sem limite
	Timeout    time.Duration
}

func envOr(key, fallback string) string {
	if v := strings.TrimSpace(os.Getenv(key)); v != "" {
		return v
	}
	return fallback
}

func envInt(key string, fallback int) int {
	if v, err := strconv.Atoi(strings.TrimSpace(os.Getenv(key))); err == nil {
		return v
	}
	return fallback
}

func envFloat(key string, fallback float64) float64 {
	if v, err := strconv.ParseFloat(strings.TrimSpace(os.Getenv(key)), 64); err == nil {
		return v
	}
	return fallback
}

// geminiKey só é liberada quando o operador declara nível pago,
// porque o nível gratuito usa o conteúdo para treinar modelos (LGPD).
func geminiKey() string {
	if strings.EqualFold(os.Getenv("GEMINI_PAID_TIER"), "true") {
		return strings.TrimSpace(os.Getenv("GEMINI_API_KEY"))
	}
	return ""
}

var allowedGeminiModels = map[string]bool{
	"gemini-3.1-flash-lite": true,
	"gemini-3.5-flash-lite": true,
	"gemini-3.6-flash":      true,
	"gemini-3.8-flash":      true,
}

func geminiModel() string {
	m := envOr("GEMINI_MODEL", "gemini-3.1-flash-lite")
	if !allowedGeminiModels[m] {
		log.Printf("[WARN] GEMINI_MODEL %q fora da lista permitida; usando gemini-3.1-flash-lite", m)
		return "gemini-3.1-flash-lite"
	}
	return m
}

func loadProviders() []aiProvider {
	order := strings.Split(envOr("AI_PROVIDER_ORDER", "local,groq,cerebras,gemini,openai"), ",")
	var out []aiProvider
	for _, name := range order {
		switch strings.TrimSpace(name) {
		case "local":
			if u := strings.TrimSpace(os.Getenv("LOCAL_LLM_URL")); u != "" {
				out = append(out, aiProvider{Name: "local-llm", Kind: "openai-compatible", BaseURL: strings.TrimRight(u, "/"),
					APIKey: os.Getenv("LOCAL_LLM_API_KEY"), Model: envOr("LOCAL_LLM_MODEL", "qwen3.5-2b"),
					Timeout: time.Duration(envInt("LOCAL_LLM_TIMEOUT_SECONDS", 20)) * time.Second})
			}
		case "groq":
			if k := os.Getenv("GROQ_API_KEY"); k != "" {
				out = append(out, aiProvider{Name: "groq", Kind: "openai-compatible", BaseURL: "https://api.groq.com/openai/v1",
					APIKey: k, Model: envOr("GROQ_MODEL", "llama-3.3-70b-versatile"),
					DailyLimit: envInt("GROQ_DAILY_LIMIT", 950), Timeout: 10 * time.Second})
			}
		case "cerebras":
			if k := os.Getenv("CEREBRAS_API_KEY"); k != "" {
				out = append(out, aiProvider{Name: "cerebras", Kind: "openai-compatible", BaseURL: "https://api.cerebras.ai/v1",
					APIKey: k, Model: envOr("CEREBRAS_MODEL", "llama-3.3-70b"),
					DailyLimit: envInt("CEREBRAS_DAILY_LIMIT", 600), Timeout: 10 * time.Second})
			}
		case "gemini":
			if k := geminiKey(); k != "" {
				out = append(out, aiProvider{Name: "gemini", Kind: "gemini", APIKey: k, Model: geminiModel(), Timeout: 15 * time.Second})
			}
		case "openai":
			if k := os.Getenv("OPENAI_API_KEY"); k != "" {
				out = append(out, aiProvider{Name: "openai", Kind: "openai", APIKey: k, Model: envOr("OPENAI_MODEL", "gpt-4o-mini"), Timeout: 15 * time.Second})
			}
		}
	}
	return out
}

// ── Cotas diárias por provedor (memória; zera à meia-noite UTC) ──

type quotaTracker struct {
	mu           sync.Mutex
	day          string
	used         map[string]int
	blockedUntil map[string]time.Time
}

var quotas = &quotaTracker{used: map[string]int{}, blockedUntil: map[string]time.Time{}}

func (q *quotaTracker) allow(p aiProvider) bool {
	q.mu.Lock()
	defer q.mu.Unlock()
	today := time.Now().UTC().Format("2006-01-02")
	if q.day != today {
		q.day, q.used = today, map[string]int{}
	}
	if until, ok := q.blockedUntil[p.Name]; ok && time.Now().Before(until) {
		return false
	}
	return p.DailyLimit == 0 || q.used[p.Name] < p.DailyLimit
}

func (q *quotaTracker) record(p aiProvider) {
	q.mu.Lock()
	q.used[p.Name]++
	q.mu.Unlock()
}

func (q *quotaTracker) block(p aiProvider, d time.Duration) {
	q.mu.Lock()
	q.blockedUntil[p.Name] = time.Now().Add(d)
	q.mu.Unlock()
}

// ── Chamada genérica a APIs compatíveis com OpenAI (Groq, Cerebras, llamafile) ──

const jsonShapeInstruction = `Responda SOMENTE com um objeto JSON neste formato exato, sem texto extra:
{"kind":"finance|task","confidence":0.0-1.0,"data":{"type":"income|expense|null","description":"string|null","amount":number|null,"occurred_on":"YYYY-MM-DD|null","name":"string|null","priority":"low|medium|high|null","status":"todo|doing|done|null","due_on":"YYYY-MM-DD|null","category_id":integer|null}}`

func callOpenAICompatible(ctx context.Context, p aiProvider, prompt string) (*ParseResponse, *Usage, error) {
	body, _ := json.Marshal(map[string]any{
		"model":           p.Model,
		"temperature":     0,
		"max_tokens":      300,
		"response_format": map[string]string{"type": "json_object"},
		"messages": []map[string]string{
			{"role": "system", "content": "Você extrai dados estruturados para a assistente financeira Mia. " + jsonShapeInstruction},
			{"role": "user", "content": prompt},
		},
	})
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, p.BaseURL+"/chat/completions", bytes.NewReader(body))
	if err != nil {
		return nil, nil, err
	}
	req.Header.Set("Content-Type", "application/json")
	if p.APIKey != "" {
		req.Header.Set("Authorization", "Bearer "+p.APIKey)
	}
	resp, err := httpClient.Do(req)
	if err != nil {
		return nil, nil, fmt.Errorf("%s indisponível: %w", p.Name, errors.Unwrap(err))
	}
	defer resp.Body.Close()
	raw, _ := io.ReadAll(io.LimitReader(resp.Body, maxProviderResponseBytes))
	if resp.StatusCode == http.StatusTooManyRequests {
		return nil, nil, errQuotaExceeded
	}
	if resp.StatusCode != http.StatusOK {
		return nil, nil, fmt.Errorf("%s retornou status %d", p.Name, resp.StatusCode)
	}
	var out struct {
		Choices []struct {
			Message struct {
				Content string `json:"content"`
			} `json:"message"`
		} `json:"choices"`
		Usage struct {
			PromptTokens     int `json:"prompt_tokens"`
			CompletionTokens int `json:"completion_tokens"`
		} `json:"usage"`
	}
	if err := json.Unmarshal(raw, &out); err != nil || len(out.Choices) == 0 {
		return nil, nil, fmt.Errorf("%s: resposta inválida", p.Name)
	}
	content := strings.TrimSpace(out.Choices[0].Message.Content)
	content = strings.TrimSuffix(strings.TrimPrefix(strings.TrimPrefix(content, "```json"), "```"), "```")
	parsed, err := parseAIResponse(strings.TrimSpace(content), p.Name)
	if err != nil {
		return nil, nil, fmt.Errorf("%s: JSON inválido", p.Name)
	}
	return parsed, &Usage{Provider: p.Name, Model: p.Model, InputTokens: out.Usage.PromptTokens, OutputTokens: out.Usage.CompletionTokens}, nil
}

func validParsed(r *ParseResponse) bool {
	if r == nil {
		return false
	}
	switch r.Kind {
	case "finance":
		return r.Data.Amount != nil && *r.Data.Amount > 0 && r.Data.Type != nil
	case "task":
		return r.Data.Name != nil && strings.TrimSpace(*r.Data.Name) != ""
	}
	return false
}

// routeParse percorre a cascata respeitando o orçamento total de tempo do contexto.
func routeParse(ctx context.Context, req ParseRequest) ParseResponse {
	local := finalizeParsed(localParse(req), req)
	accept := envFloat("LOCAL_ACCEPT_CONFIDENCE", 0.82)
	if local.Kind == "finance" && local.Confidence >= accept && local.Data.CategoryID != nil && validParsed(&local) {
		local.Usage = &Usage{Provider: "local", Model: "regex"}
		return local
	}

	prompt := promptFor(req)
	for _, p := range loadProviders() {
		if ctx.Err() != nil {
			break
		}
		if !quotas.allow(p) {
			continue
		}
		pctx, cancel := context.WithTimeout(ctx, p.Timeout)
		var (
			r     *ParseResponse
			usage *Usage
			err   error
		)
		switch p.Kind {
		case "openai-compatible":
			r, usage, err = callOpenAICompatible(pctx, p, prompt)
		case "gemini":
			r, err = callGemini(pctx, p.APIKey, p.Model, []GeminiPart{{Text: prompt}})
			usage = &Usage{Provider: p.Name, Model: p.Model}
		case "openai":
			r, err = callOpenAI(pctx, p.APIKey, p.Model, prompt)
			usage = &Usage{Provider: p.Name, Model: p.Model}
		}
		cancel()
		if errors.Is(err, errQuotaExceeded) {
			quotas.block(p, 15*time.Minute)
			log.Printf("[WARN] %s: cota esgotada; pausado por 15 min", p.Name)
			continue
		}
		if err != nil || !validParsed(r) {
			log.Printf("[WARN] %s falhou; seguindo a cascata", p.Name)
			continue
		}
		quotas.record(p)
		result := finalizeParsed(*r, req)
		result.Usage = usage
		return result
	}
	local.Usage = &Usage{Provider: "local", Model: "regex"}
	return local
}
