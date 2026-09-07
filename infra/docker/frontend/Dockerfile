FROM node:22-alpine AS build

WORKDIR /app

COPY frontend/package*.json ./
RUN npm ci

COPY frontend/ ./

# Vite baker VITE_* no build (não em runtime) para que o frontend consiga acessar as variáveis de ambiente
ARG VITE_GOOGLE_CLIENT_ID
ENV VITE_GOOGLE_CLIENT_ID=$VITE_GOOGLE_CLIENT_ID

ARG VITE_GOOGLE_MAPS_API_KEY
ENV VITE_GOOGLE_MAPS_API_KEY=$VITE_GOOGLE_MAPS_API_KEY

RUN npm run build

FROM nginx:1.29-alpine

# BACKEND_UPSTREAM: nome do serviço + porta do backend -- `backend:9000` bate com o
# docker-compose local; em k8s, o Deployment troca isso via env pro nome do Service
ENV BACKEND_UPSTREAM=backend:9000
ENV NGINX_ENVSUBST_FILTER=BACKEND_UPSTREAM

COPY --from=build /app/dist /usr/share/nginx/html
COPY infra/docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template