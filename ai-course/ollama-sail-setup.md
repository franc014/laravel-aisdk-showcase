# Installing Ollama in Laravel Sail

This guide shows you how to run Ollama inside your Laravel Sail Docker environment instead of on your host machine. This keeps everything containerized and simplifies networking.

## Option 1: Add Ollama as a Separate Service (Recommended)

This creates a dedicated Ollama container alongside your Laravel app and PostgreSQL.

### Step 1: Update docker-compose.yml

Open your `docker-compose.yml` file and add an `ollama` service:

```yaml
version: '3'
services:
    # ... existing laravel.test service ...

    pgsql:
        # ... existing pgsql config ...

    # Add this new service
    ollama:
        image: ollama/ollama:latest
        container_name: laravel-ollama
        ports:
            - '11434:11434'
        volumes:
            - ollama-data:/root/.ollama
        environment:
            - OLLAMA_KEEP_ALIVE=24h
        networks:
            - sail
        # For GPU support (Linux only), uncomment:
        # deploy:
        #     resources:
        #         reservations:
        #             devices:
        #                 - driver: nvidia
        #                   count: 1
        #                   capabilities: [gpu]

# Add volume for Ollama models
volumes:
    sail-pgsql:
        driver: local
    ollama-data:
        driver: local

# ... existing networks ...
```

### Step 2: Update .env Configuration

Update your `.env` file to connect to Ollama inside the container:

```env
# Use the service name 'ollama' for internal Docker networking
OLLAMA_URL=http://ollama:11434

# Or if accessing from host machine for testing:
# OLLAMA_URL=http://localhost:11434
```

### Step 3: Start the Containers

```bash
./vendor/bin/sail up -d
```

### Step 4: Download Models

Enter the Ollama container and pull models:

```bash
# Enter the Ollama container
./vendor/bin/sail exec ollama bash

# Pull text generation model
ollama pull llama3.1:8b

# Pull embedding model
ollama pull nomic-embed-text

# Exit container
exit
```

### Step 5: Test Ollama

From your host machine:

```bash
curl http://localhost:11434/api/tags
```

You should see the downloaded models listed.

From Laravel:

```bash
./vendor/bin/sail artisan tinker
```

```php
use Laravel\Ai\Facades\Ai;

// Test Ollama connection
$response = Ai::via('ollama')->text('Hello!');
echo $response;
```

---

## Option 2: Install Ollama in the Laravel App Container

If you prefer Ollama to be in the same container as your app:

### Step 1: Create a Custom Dockerfile

Create `docker/8.4/Dockerfile.ollama`:

```dockerfile
FROM ubuntu:24.04

# Install dependencies
RUN apt-get update && apt-get install -y \
    curl \
    ca-certificates \
    systemd \
    && rm -rf /var/lib/apt/lists/*

# Install Ollama
RUN curl -fsSL https://ollama.com/install.sh | sh

# Install PHP and Laravel dependencies (standard Sail setup)
RUN apt-get update && apt-get install -y \
    php8.4 \
    php8.4-cli \
    php8.4-fpm \
    php8.4-pgsql \
    php8.4-sqlite3 \
    php8.4-gd \
    php8.4-curl \
    php8.4-imap \
    php8.4-mbstring \
    php8.4-xml \
    php8.4-zip \
    php8.4-bcmath \
    php8.4-soap \
    php8.4-intl \
    php8.4-readline \
    php8.4-ldap \
    php8.4-msgpack \
    php8.4-igbinary \
    php8.4-redis \
    php8.4-swoole \
    php8.4-memcached \
    php8.4-pcov \
    php8.4-xdebug \
    nodejs \
    npm \
    && rm -rf /var/lib/apt/lists/*

# Create start script
RUN echo '#!/bin/bash\n\
    # Start Ollama in background\n\
    ollama serve &\n\
    sleep 5\n\
    # Pull models if not present\n\
    ollama list | grep -q llama3.1 || ollama pull llama3.1:8b\n\
    ollama list | grep -q nomic || ollama pull nomic-embed-text\n\
    # Start PHP-FPM\n\
    php-fpm8.4\n\
    ' > /start.sh && chmod +x /start.sh

EXPOSE 8000 11434

CMD ["/start.sh"]
```

### Step 2: Update docker-compose.yml

Change the `laravel.test` service to use your custom Dockerfile:

```yaml
laravel.test:
    build:
        context: ./docker/8.4
        dockerfile: Dockerfile.ollama
    # ... rest of config ...
```

---

## Option 3: Use Ollama Docker Compose Override (Quick Setup)

Create a `docker-compose.override.yml` file:

```yaml
version: '3'
services:
    ollama:
        image: ollama/ollama:latest
        container_name: sail-ollama
        ports:
            - '11434:11434'
        volumes:
            - ollama-models:/root/.ollama
        networks:
            - sail
        environment:
            - OLLAMA_KEEP_ALIVE=24h
            - OLLAMA_ORIGINS=*
        # Uncomment for GPU support on Linux:
        # runtime: nvidia
        # environment:
        #     - NVIDIA_VISIBLE_DEVICES=all

volumes:
    ollama-models:
        driver: local
```

Docker Compose automatically merges this with your main `docker-compose.yml`.

---

## Laravel AI SDK Configuration for Sail Ollama

### Config/ai.php Settings

Update your `config/ai.php`:

```php
'providers' => [
    'ollama' => [
        'driver' => 'ollama',
        // Use service name for internal Docker networking
        'url' => env('OLLAMA_URL', 'http://ollama:11434'),
        'models' => [
            'text' => 'llama3.1:8b',
            'embedding' => 'nomic-embed-text',
        ],
    ],
    // ... other providers ...
],
```

### Environment Variables

```env
# For Docker networking (when both are in containers)
OLLAMA_URL=http://ollama:11434

# For host access (testing from your machine)
# OLLAMA_URL=http://localhost:11434
```

---

## Managing Ollama in Sail

### Pull New Models

```bash
# Enter Ollama container
./vendor/bin/sail exec ollama bash

# Pull a model
ollama pull llama3.1:8b
ollama pull llama3.2:3b
ollama pull codellama:7b
ollama pull nomic-embed-text

# List downloaded models
ollama list

# Remove a model
ollama rm llama3.1:8b

# Exit
exit
```

### Check Ollama Logs

```bash
# View logs
./vendor/bin/sail logs ollama

# Follow logs in real-time
./vendor/bin/sail logs -f ollama
```

### Restart Ollama

```bash
./vendor/bin/sail restart ollama
```

### Test from Laravel Container

```bash
./vendor/bin/sail exec laravel.test bash

# Test with curl
curl http://ollama:11434/api/tags

# Or use PHP
php artisan tinker
```

```php
use Laravel\Ai\Facades\Ai;

$response = Ai::via('ollama')->text('Hi!');
```

---

## GPU Support (Optional)

For faster inference with GPU acceleration (Linux hosts with NVIDIA GPU):

### 1. Install NVIDIA Docker Runtime

```bash
# Add nvidia-docker2 package
distribution=$(. /etc/os-release;echo $ID$VERSION_ID)
curl -s -L https://nvidia.github.io/nvidia-docker/gpgkey | sudo apt-key add -
curl -s -L https://nvidia.github.io/nvidia-docker/$distribution/nvidia-docker.list | sudo tee /etc/apt/sources.list.d/nvidia-docker.list

sudo apt-get update
sudo apt-get install -y nvidia-docker2
sudo systemctl restart docker
```

### 2. Update docker-compose.yml

Add GPU configuration to the ollama service:

```yaml
ollama:
    image: ollama/ollama:latest
    container_name: sail-ollama
    ports:
        - '11434:11434'
    volumes:
        - ollama-data:/root/.ollama
    environment:
        - OLLAMA_KEEP_ALIVE=24h
    networks:
        - sail
    deploy:
        resources:
            reservations:
                devices:
                    - driver: nvidia
                      count: 1
                      capabilities: [gpu]
```

### 3. Verify GPU Access

```bash
./vendor/bin/sail exec ollama nvidia-smi
```

---

## Troubleshooting

### Connection Refused

**Problem:** Laravel can't connect to Ollama

**Solution:**

```bash
# Check if Ollama container is running
./vendor/bin/sail ps

# Check Ollama logs
./vendor/bin/sail logs ollama

# Test from Laravel container
./vendor/bin/sail exec laravel.test curl http://ollama:11434/api/tags
```

### Model Not Found

**Problem:** Model doesn't exist error

**Solution:**

```bash
# Enter Ollama container and pull model
./vendor/bin/sail exec ollama bash
ollama pull llama3.1:8b
exit
```

### Slow Performance

**Problem:** Ollama is slow on CPU

**Solutions:**

1. Use smaller models (llama3.2:3b instead of 8b)
2. Enable GPU support (see GPU section)
3. Increase container memory limits in docker-compose.yml

### CORS Errors

**Problem:** Browser can't access Ollama from frontend

**Solution:**
Add to docker-compose.yml environment:

```yaml
environment:
    - OLLAMA_ORIGINS=*
    - OLLAMA_HOST=0.0.0.0
```

---

## Recommended Setup Summary

The cleanest approach is **Option 1** (separate service):

1. Add `ollama` service to `docker-compose.yml`
2. Set `OLLAMA_URL=http://ollama:11434` in `.env`
3. Run `./vendor/bin/sail up -d`
4. Pull models: `./vendor/bin/sail exec ollama ollama pull llama3.1:8b`
5. Test: `curl http://localhost:11434/api/tags`

This keeps Ollama isolated, makes it easy to manage, and allows GPU passthrough if needed.
