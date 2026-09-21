# ---- Stage 1: Builder ----
FROM golang:1.22-alpine AS builder

WORKDIR /app

COPY . .
RUN go mod tidy
RUN CGO_ENABLED=0 GOOS=linux go build -ldflags="-s -w" -o api .

# ---- Stage 2: Runtime ----
FROM alpine:latest

RUN apk add --no-cache ca-certificates tzdata wget

WORKDIR /app

COPY --from=builder /app/api .

EXPOSE 8000

CMD ["./api"]
