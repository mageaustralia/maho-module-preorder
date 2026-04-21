.PHONY: up down logs shell smoke reset

up:
	docker compose -f docker-compose.yml up -d --wait

down:
	docker compose -f docker-compose.yml down

logs:
	docker compose -f docker-compose.yml logs -f maho

shell:
	docker compose -f docker-compose.yml exec maho bash

smoke:
	curl -fsS http://localhost:8080/ > /dev/null && echo "✓ frontend reachable"
	curl -fsS http://localhost:8080/preorder > /dev/null && echo "✓ /preorder reachable" || echo "✗ /preorder not reachable yet (expected pre-controller)"

reset:
	docker compose -f docker-compose.yml down -v
