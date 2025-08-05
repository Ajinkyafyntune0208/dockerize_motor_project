# Stage 1: Build React app
FROM node:18-alpine AS builder

WORKDIR /app
COPY . .

# Install dependencies forcefully to resolve peer deps issues
RUN npm install --legacy-peer-deps --force

# Build the project
RUN npm run build

# Stage 2: Serve with Nginx
FROM nginx:alpine

# Copy build output to Nginx public folder
COPY --from=builder /app/build /usr/share/nginx/html

# Copy custom Nginx config if exists
COPY ./nginx.conf /etc/nginx/conf.d/default.conf
