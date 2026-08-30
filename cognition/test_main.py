from io import BytesIO
from unittest import IsolatedAsyncioTestCase
from unittest.mock import AsyncMock, patch

from fastapi import UploadFile

from main import ParseRequest, parse_audio, parse_request


class ProviderPriorityTest(IsolatedAsyncioTestCase):
    async def test_gemini_is_the_default_primary_provider(self) -> None:
        request = ParseRequest(text="Paguei 10 reais", gemini_api_key="gemini", openai_api_key="openai")
        gemini_result = {"provider": "gemini", "kind": "finance", "data": {}}

        with (
            patch("main.parse_with_gemini", new=AsyncMock(return_value=gemini_result)) as gemini,
            patch("main.parse_with_openai", new=AsyncMock()) as openai,
        ):
            result = await parse_request(request)

        self.assertEqual("gemini", result["provider"])
        gemini.assert_awaited_once()
        openai.assert_not_awaited()

    async def test_openai_is_used_when_gemini_fails(self) -> None:
        request = ParseRequest(text="Paguei 10 reais", gemini_api_key="gemini", openai_api_key="openai")
        openai_result = {"provider": "openai", "kind": "finance", "data": {}}

        with (
            patch("main.parse_with_gemini", new=AsyncMock(side_effect=RuntimeError("unavailable"))),
            patch("main.parse_with_openai", new=AsyncMock(return_value=openai_result)) as openai,
        ):
            result = await parse_request(request)

        self.assertEqual("openai", result["provider"])
        openai.assert_awaited_once()

    async def test_audio_goes_directly_to_gemini(self) -> None:
        upload = UploadFile(file=BytesIO(b"small audio"), filename="voice.ogg", headers={"content-type": "audio/ogg"})
        gemini_result = {"provider": "gemini", "audio_provider": "gemini", "kind": "finance", "data": {}}

        with patch("main.parse_audio_with_gemini", new=AsyncMock(return_value=gemini_result)) as gemini:
            result = await parse_audio(
                file=upload,
                categories="[]",
                context="Mensagem recebida por áudio.",
                primary_provider="gemini",
                openai_api_key=None,
                openai_model="gpt-5-mini",
                gemini_api_key="gemini",
                gemini_model="gemini-3.6-flash",
            )

        self.assertEqual("gemini", result["audio_provider"])
        gemini.assert_awaited_once()


if __name__ == "__main__":
    import unittest

    unittest.main()
