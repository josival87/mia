package main

import (
	"context"
	"fmt"
	"net/http"
	"net/http/httptest"
	"testing"
)

var testCategories = []Category{{1, "Alimentação", "expense"}, {2, "Transporte", "expense"}, {3, "Salário", "income"}}

func TestLocalParserResolvesSimpleMessagesWithoutAI(t *testing.T) {
	for msg, want := range map[string]float64{"gastei 50 no mercado": 50, "uber 23": 23, "recebi 3200 de salário": 3200} {
		r := routeParse(context.Background(), ParseRequest{Text: msg, Categories: testCategories})
		if r.Data.Amount == nil || *r.Data.Amount != want {
			t.Fatalf("%q: valor esperado %.2f", msg, want)
		}
		if r.Usage == nil || r.Usage.Provider != "local" {
			t.Fatalf("%q: deveria ser resolvida sem IA", msg)
		}
	}
}

func TestCascadeUsesOpenAICompatibleLocalLLM(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		fmt.Fprint(w, `{"choices":[{"message":{"content":"{\"kind\":\"finance\",\"confidence\":0.9,\"data\":{\"type\":\"expense\",\"description\":\"Café João\",\"amount\":15,\"occurred_on\":\"2026-11-02\",\"name\":null,\"priority\":null,\"status\":null,\"due_on\":null,\"category_id\":1}}"}}],"usage":{"prompt_tokens":400,"completion_tokens":60}}`)
	}))
	defer srv.Close()
	t.Setenv("LOCAL_LLM_URL", srv.URL)
	r := routeParse(context.Background(), ParseRequest{Text: "pix pro joão do café, quinze", Categories: testCategories})
	if r.Usage == nil || r.Usage.Provider != "local-llm" || *r.Data.Amount != 15 {
		t.Fatalf("esperava resposta do LLM local, veio %+v", r.Usage)
	}
}

func TestQuotaExceededSkipsProvider(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) { w.WriteHeader(429) }))
	defer srv.Close()
	t.Setenv("LOCAL_LLM_URL", srv.URL)
	r := routeParse(context.Background(), ParseRequest{Text: "pix pro joão do café, quinze", Categories: testCategories})
	if r.Usage == nil || r.Usage.Provider != "local" {
		t.Fatalf("com cota esgotada deveria cair no parser local, veio %+v", r.Usage)
	}
}
