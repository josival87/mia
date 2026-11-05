package main

import (
	"context"
	"encoding/csv"
	"fmt"
	"math"
	"os"
	"sort"
	"strconv"
	"strings"
	"testing"
	"time"
)

var benchmarkCategories = []Category{
	{1, "Alimentação", "expense"},
	{2, "Transporte", "expense"},
	{3, "Moradia", "expense"},
	{4, "Saúde", "expense"},
	{5, "Lazer", "expense"},
	{6, "Educação", "expense"},
	{7, "Salário", "income"},
	{8, "Outros", "expense"},
}

type benchmarkSample struct {
	text             string
	expectedType     string
	expectedAmount   float64
	expectedCategory string
}

func loadBenchmarkCSV(path string) ([]benchmarkSample, error) {
	f, err := os.Open(path)
	if err != nil {
		return nil, err
	}
	defer f.Close()

	r := csv.NewReader(f)
	records, err := r.ReadAll()
	if err != nil {
		return nil, err
	}

	var samples []benchmarkSample
	for i, row := range records {
		if i == 0 || len(row) < 4 { // skip header
			continue
		}
		amt, err := strconv.ParseFloat(strings.TrimSpace(row[2]), 64)
		if err != nil {
			continue
		}
		samples = append(samples, benchmarkSample{
			text:             strings.TrimSpace(row[0]),
			expectedType:     strings.TrimSpace(row[1]),
			expectedAmount:   amt,
			expectedCategory: strings.TrimSpace(row[3]),
		})
	}
	return samples, nil
}

func TestBenchmarkMasterplan20Messages(t *testing.T) {
	csvPath := "benchmark_20_messages.csv"
	samples, err := loadBenchmarkCSV(csvPath)
	if err != nil {
		t.Fatalf("Erro ao carregar %s: %v", csvPath, err)
	}

	if len(samples) < 20 {
		t.Fatalf("Esperava pelo menos 20 amostras no benchmark, encontrou %d", len(samples))
	}

	var typeCorrect, amountCorrect, categoryCorrect, allCorrect int
	var latencies []time.Duration
	var under5sCount int

	fmt.Printf("\n=== INICIANDO BENCHMARK DE 20 MENSAGENS ===\n")
	providerName := "local (regex)"
	if u := os.Getenv("LOCAL_LLM_URL"); u != "" {
		providerName = fmt.Sprintf("local-llm (%s)", u)
	}
	fmt.Printf("Provedor sob teste: %s\n", providerName)

	for i, s := range samples {
		start := time.Now()
		res := routeParse(context.Background(), ParseRequest{
			Text:       s.text,
			Categories: benchmarkCategories,
		})
		elapsed := time.Since(start)
		latencies = append(latencies, elapsed)

		if elapsed < 5*time.Second {
			under5sCount++
		}

		gotType := ""
		if res.Data.Type != nil {
			gotType = *res.Data.Type
		}
		gotAmount := 0.0
		if res.Data.Amount != nil {
			gotAmount = *res.Data.Amount
		}
		gotCategory := ""
		if res.Data.CategoryID != nil {
			for _, c := range benchmarkCategories {
				if c.ID == *res.Data.CategoryID {
					gotCategory = c.Name
					break
				}
			}
		}

		typeMatches := strings.EqualFold(gotType, s.expectedType)
		amountMatches := math.Abs(gotAmount-s.expectedAmount) < 0.01
		categoryMatches := strings.EqualFold(gotCategory, s.expectedCategory)

		if typeMatches {
			typeCorrect++
		}
		if amountMatches {
			amountCorrect++
		}
		if categoryMatches {
			categoryCorrect++
		}
		if typeMatches && amountMatches && categoryMatches {
			allCorrect++
		}

		status := "✓"
		if !(typeMatches && amountMatches && categoryMatches) {
			status = "✗"
		}

		fmt.Printf("[%02d] %s \"%s\" -> tipo: %s (%v), valor: %.2f (%v), cat: %s (%v) [%v]\n",
			i+1, status, s.text, gotType, typeMatches, gotAmount, amountMatches, gotCategory, categoryMatches, elapsed)
	}

	sort.Slice(latencies, func(i, j int) bool { return latencies[i] < latencies[j] })
	p95Index := int(math.Ceil(float64(len(latencies))*0.95)) - 1
	if p95Index >= len(latencies) {
		p95Index = len(latencies) - 1
	}
	p95Latency := latencies[p95Index]

	n := float64(len(samples))
	typeAcc := (float64(typeCorrect) / n) * 100
	amountAcc := (float64(amountCorrect) / n) * 100
	categoryAcc := (float64(categoryCorrect) / n) * 100
	allAcc := (float64(allCorrect) / n) * 100
	under5sPercent := (float64(under5sCount) / n) * 100

	fmt.Printf("\n=== RESULTADOS DO BENCHMARK ===\n")
	fmt.Printf("Acurácia de Tipo:      %.1f%% (%d/%d)\n", typeAcc, typeCorrect, len(samples))
	fmt.Printf("Acurácia de Valor:     %.1f%% (%d/%d)\n", amountAcc, amountCorrect, len(samples))
	fmt.Printf("Acurácia de Categoria: %.1f%% (%d/%d)\n", categoryAcc, categoryCorrect, len(samples))
	fmt.Printf("Acurácia Conjunta:     %.1f%% (%d/%d)\n", allAcc, allCorrect, len(samples))
	fmt.Printf("Latência p95:          %v\n", p95Latency)
	fmt.Printf("Respostas < 5s:        %.1f%% (%d/%d)\n", under5sPercent, under5sCount, len(samples))

	adotar := typeAcc >= 95.0 && amountAcc >= 95.0 && categoryAcc >= 95.0 && under5sPercent >= 95.0
	fmt.Printf("Critério de Adoção (>=95%% acerto e 95%% < 5s): %v\n", adotar)
	if !adotar {
		fmt.Printf("RECOMENDAÇÃO: NÃO ADOTAR local-llm como primário até que atinja 95%% de acerto em tipo, valor e categoria e <5s.\n")
	} else {
		fmt.Printf("RECOMENDAÇÃO: APROVADO para adoção local-llm.\n")
	}
}

