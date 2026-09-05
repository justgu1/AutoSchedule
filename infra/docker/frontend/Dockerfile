FROM node:22-alpine AS build

WORKDIR /app

COPY frontend/package*.json ./
RUN npm ci

COPY frontend/ ./
RUN npm run build

FROM nginx:1.29-alpine

# BACKEND_UPSTREAM: nome do serviço + porta do backend -- `backend:9000` bate com o
# docker-compose local (não muda nada pra quem já usa o compose); em k8s, o Deployment
# troca isso via env pro nome do Service (ex: autoschedule-backend:9000), já que
# `apps` é um namespace compartilhado entre vários apps, sem service literal `backend`.
ENV BACKEND_UPSTREAM=backend:9000
ENV NGINX_ENVSUBST_FILTER=BACKEND_UPSTREAM

COPY --from=build /app/dist /usr/share/nginx/html
COPY infra/docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template