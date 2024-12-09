# Laralord Project

Laralord enables multi-tenancy for any stateless Laravel application without requiring code updates.
It is an OpenSwoole wrapper designed to provide a high-performance, multi-tenant server setup for Laravel Application.

---

## Key Features

1. **Multi-Tenant Support**:
    - Detect tenant ID via headers, cookies, JWT tokens, query strings, or POST data.
    - Operate in:
        - **Single-Tenant Mode**: Optimized server for a single tenant with pre-booted Laravel instances for ultra-fast
          responses.
        - **Multi-Tenant Mode**: Dynamically resolve environment credentials per tenant, supporting concurrent requests
          with isolated environments.
        - **S3 Bucket Proxy**: Simplifies serving static files and tenant-specific frontends from an S3 or S3-compatible
          bucket.

2. **High Performance**:
    - Pre-boot Laravel application in **Single-Tenant Mode**, reducing response delay by up to 80ms.
    - **Process Isolation**: Each request is executed in a separate process using the `pcntl` extension.

3. **Unified Queue Workers and Scheduler**:
    - Fair distribution of resources between tenants in both the queue system and scheduler.
    - Supports isolated task execution per tenant, ensuring that each tenant has equal access to resources.

4. **Dynamic Credentials Update**:
    - Periodically fetch and update credentials from HashiCorp Vault, ensuring that tenant environments are always using
      the latest credentials.

5. **Containerization Support**:
    - Application built for containerization, offering simple and clear configuration, even through environment
      variables.

---

## Requirements

- **PHP**: >= 8.2
- **PHP Extensions**: `openswoole`, `inotify`, `apcu`, `sysvmsg`
- **Composer**: Installed globally
- **Environment Variables Source**: Vault, file-based, or directory-based configurations supported
- **System Utilities**: `bash`, `curl`
- **Permissions**: Necessary permissions for the configured directories and Vault

---

## Components

1. **[Server](#server)**
    - [Commands](#server-commands)
    - [Configuration (Options and Environment Variables)](#server-configuration-options-and-environment-variables)
2. **[S3-Proxy](#s3-proxy)**
    - [Commands](#s3-proxy-commands)
    - [Configuration (Options and Environment Variables)](#s3-proxy-configuration-options-and-environment-variables)
3. **[Queue](#queue)**
    - [Commands](#queue-commands)
    - [Configuration (Options and Environment Variables)](#queue-configuration-options-and-environment-variables)
4. **[Scheduler](#scheduler)**
    - [Commands](#scheduler-commands)
    - [Configuration (Options and Environment Variables)](#scheduler-configuration-options-and-environment-variables)
5. **[Environment Source and Resolvers](#env-source)
6. **[Environment Helpers](#environment-helpers)**

---

## Components

### **Server**

#### Description

The Server component powers the core functionality of Laralord, dynamically resolving tenant environments and serving
requests in both single and multi-tenant modes.

#### Key Features

- Single-tenant mode with application pre-warming for faster response times.
- Multi-tenant mode with tenant-specific environment resolution.
- Dynamic environment variable management.
- Isolated request handling using the `pcntl` extension.

#### [Commands](#server-commands)

| Command                     | Description                                       |  
|-----------------------------|---------------------------------------------------|  
| `laralord server:start`     | Starts the server in single or multi-tenant mode. |  
| `laralord server env:store` | Stores environment variables for a tenant.        |  
| `laralord server help`      | Displays help for the server commands.            |  
| `laralord server version`   | Prints the server version.                        |  

#### [Configuration (Options and Environment Variables)](#server-configuration-options-and-environment-variables)

| Environment Variable           | Option                             | Default Value                   | Description                                                                                       |  
|--------------------------------|------------------------------------|---------------------------------|---------------------------------------------------------------------------------------------------|  
| `SERVER_HOST`                  | `--host=0.0.0.0`                   | `0.0.0.0`                       | Server host.                                                                                      |  
| `SERVER_PORT`                  | `--port=8000`                      | `8000`                          | Server port.                                                                                      |  
| `SERVER_WATCH`                 | `--watch=false`                    | `false`                         | Enable the WATCH mode for development.                                                            |  
| `SERVER_WATCH_TARGET`          | `--watchTargets=app,bootstrap,...` | `[app, bootstrap, config, ...]` | The array of paths for change detection.                                                          |  
| `SERVER_LOG_LEVEL`             | `--logLevel=NOTICE`                | `NOTICE`                        | Log level: DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY.                       |  
| `SERVER_ENV_FILE`              | `--configEnvFile=/secrets/.env`    | `/secrets/.laralord-env`        | Server configuration `.env` file.                                                                 |  
| `SERVER_MODE`                  | `--mode=single`                    | `single`                        | Environment resolver mode: single, multi-tenant.                                                  |  
| `SERVER_WARM_UP`               | `--warmUp=/healthcheck`            | `/healthcheck`                  | Endpoint for warming up Laravel applications in single mode.                                      |  
| `SERVER_TENANT_KEY`            | `--tenantKey=header.TENANT-ID`     | `header.TENANT-ID`              | Tenant resolve method: header, jwt, oidc, cookie.                                                 |  
| `OPTION_WORKERS`               | `--workerNum=10`                   | `10`                            | Number of worker processes to start. By default, this is set to the number of CPU cores you have. |
| `OPTION_STATIC_FILES_ENABLED`  | `--enableStaticHandler=false`      | `false`                         | Enable the static handler for development.                                                        |  
| `OPTION_DOCUMENT_ROOT`         | `--documentRoot=/var/www/public`   | `/var/www/public`               | Base path for serving static files.                                                               |  
| `OPTION_STATIC_FILE_LOCATIONS` | `--staticHandlerLocations=[ ]`     | `[ ]`                           | Directories allowed for static file serving.                                                      |  
| `OPTION_MAX_EXECUTION_TIME`    | `--maxRequestExecutionTime=2`      | `2`                             | Max execution time for requests, in seconds.                                                      |  
| `APP_BASE_PATH`                | `--basePath=/var/www`              | `/var/www`                      | Path to the Laravel project.                                                                      |


[Menu](#components) &nbsp; &nbsp;  [Environment Source Configuration](#env-source)


### **S3-Proxy**

#### Description

The S3-Proxy component provides a seamless way to proxy requests to S3 or S3-compatible storage backends. It has no sense as independent service, but could be very helpful in pair with gateway service

#### Key Features

- Transparent proxying of requests to S3 backends.
- Coroutine enabled for S3 client. Non-blocking requests processing.   
- Support for tenant-specific configurations in multi-tenant mode.
- Serve any number of static sites from S3 using only one server


#### How it works 

The main idea is to provide access to s3 with Frontend static files - and allow externally manage the access to this bucket.
Each request to s3 proxy should contains the header 'DOCUMENT-ROOT' which point to the specific folder(key) on S3 bucket, and file resolving by location on request. 
> **WARNING** All list requests are respond with file {DOCUMENT-ROOT}/index.html 


#### [Commands](#s3-proxy-commands)

| Command                     | Description                          |  
|-----------------------------|--------------------------------------|  
| `laralord s3-proxy:start`   | Starts the S3 proxy server.          |  
| `laralord s3-proxy help`    | Displays help for S3 proxy commands. |  
| `laralord s3-proxy version` | Prints the S3 proxy version.         |  

#### [Configuration (Options and Environment Variables)](#s3-proxy-configuration-options-and-environment-variables)

| Environment Variable         | Option                                   | Default Value                | Description                                                                            |  
|------------------------------|------------------------------------------|------------------------------|----------------------------------------------------------------------------------------|  
| `S3_PROXY_HOST`              | `--host=0.0.0.0`                         | `0.0.0.0`                    | Server host.                                                                           |  
| `S3_PROXY_PORT`              | `--port=8001`                            | `8001`                       | Server port.                                                                           |  
| `S3_PROXY_WATCH`             | `--watch=false`                          | `false`                      | Enable the WATCH mode for development.                                                 |  
| `S3_PROXY_WATCH_TARGET`      | `--watchTargets=[ ]`                     | `["/secrets/.laralord-env"]` | The array of paths for change detection.                                               |  
| `SERVER_LOG_LEVEL`           | `--logLevel=NOTICE`                      | `NOTICE`                     | Log level for server: DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY. |  
| `SERVER_ENV_FILE`            | `--configEnvFile=/secrets/.laralord-env` | `/secrets/.laralord-env`     | Server configuration `.env` file.                                                      |  
| `SERVER_ENV_SOURCE`          | `--envSource=file`                       | `file`                       | Environment variables source, options: `file`, `vault`.                                |  
| `S3_CACHE_DIR`               | `--cacheDir=/tmp/s3-proxy`               | `/tmp/s3-proxy`              | The directory to store the cache.                                                      |  
| `S3_PROXY_BUCKET`            | `--bucket=""`                            | `""`                         | S3 bucket name.                                                                        |  
| `S3_PROXY_REGION`            | `--region=""`                            | `""`                         | S3 bucket region.                                                                      |  
| `S3_PROXY_ACCESS_KEY`        | `--accessKey=""`                         | `""`                         | S3 Access Key ID.                                                                      |  
| `S3_PROXY_SECRET_KEY`        | `--secretKey=""`                         | `""`                         | S3 Secret Key.                                                                         |  
| `S3_PROXY_S3_ENDPOINT`       | `--s3Endpoint=""`                        | `""`                         | S3 Endpoint.                                                                           |  
| `S3_PROXY_S3_SCHEME`         | `--s3Scheme=https`                       | `https`                      | S3 scheme (e.g., `http`, `https`).                                                     |  
| `S3_PROXY_S3_USE_PATH_STYLE` | `--s3UsePathStyle=false`                 | `false`                      | Enable or disable path-style addressing for S3.                                        |  
| `ENV_FILE`                   | `--env_file=/var/www/.env`               | `/var/www/.env`              | Path to the project's `.env` file.                                                     |  
| `ENV_FILE_UPDATE`            | `--env_file_update=false`                | `false`                      | Specifies if the `.env` file update is required when Vault secrets are updated.        |  
| `OPTION_S3_PROXY_WORKERS`    | `--workerNum=10`                         | `10`                         | Number of worker processes to start. Default is based on CPU cores.                    |  
| `OPTION_TASK_WORKER`         | `--taskWorkerNum=0`                      | `0`                          | Set the number of task worker processes to create.                                     |  
| `OPTION_USER`                | `--user=www`                             | `www`                        | Set the operating system user for worker and task worker child processes.              |  
| `OPTION_GROUP`               | `--group=www`                            | `www`                        | Set the operating system group for worker and task worker child processes.             |  
| `OPTION_MAX_EXECUTION_TIME`  | `--maxRequestExecutionTime=2`            | `2`                          | Maximum execution time for HTTP server requests, in seconds.                           |  

[Menu](#components)

---

### **Queue**

#### Description

The Queue component manages worker processes for handling jobs in a multi-tenant environment, ensuring efficient and fair resource distribution across tenants.

#### How it Works...

The queue's main process loads the environment variables for each tenant and spawns child processes. The number of workers is specified by configuration.  
Each worker processes a set of tenants and executes `php artisan queue:work --once ...` within the context of each tenant.

**Key Mechanics:**
- Workers ensure fair resource allocation using Redis-based mutex locks.
- If a mutex lock fails, indicating a tenant already has an assigned worker, the tenant's execution is skipped, and the process moves to the next tenant.
- Worker processes fetch tenant-specific environment variables from the main process's shared memory.

The main process periodically refreshes tenants' environment configurations to ensure updates are reflected in the system.

#### Key Features

- Unified multi-tenant queue processing.
- Supports task-specific isolation.
- Tenants can use different queue drivers.
- Dynamic synchronization through Redis mutex.

#### [Commands](#queue-commands)

| Command                | Description                         |  
|------------------------|-------------------------------------|  
| `laralord queue:work`  | Starts queue workers.               |  
| `laralord queue start` | Alias for `queue:work`.             |  

#### [Configuration (Options and Environment Variables)](#queue-configuration-options-and-environment-variables)

| Environment Variable         | Option                          | Default Value                                   | Description                                                         |  
|------------------------------|---------------------------------|-----------------------------------------------|---------------------------------------------------------------------|  
| `SERVER_ENV_FILE`            | `--configEnvFile=/secrets/.laralord-env` | `/secrets/.laralord-env`                      | Path to server configuration `.env` file.                           |  
| `QUEUE_LOG_LEVEL`            | `--logLevel=NOTICE`            | `NOTICE`                                      | Log level for the server: DEBUG, INFO, NOTICE, WARNING, ERROR, etc. |  
| `SERVER_WATCH`               | `--watch=false`                | `false`                                       | Enable development WATCH mode.                                      |  
| `ENV_SOURCE`                 | `--envSource=vault`            | `vault`                                       | Source of environment variables: `vault`, `dir`.                    |  
| `QUEUE_WORKERS`              | `--workerNum=2`                | `2`                                           | Number of worker processes to start.                                |  
| `QUEUE_MAX_JOBS`             | `--maxJobs=1`                  | `1`                                           | Maximum concurrent jobs per tenant.                                 |  
| `QUEUE_LIST`                 | `--queue=default`              | `default`                                     | List of queues to process.                                          |  
| `APP_BASE_PATH`              | `--basePath=/var/www`          | `/var/www`                                    | Base path for the Laravel project.                                  |  
| `SYNC_METHOD`                | `--synchronizer=redis`         | `redis`                                       | Synchronization method for workers.                                 |  
| `SYNC_REDIS_HOST`            | `--redisHost=redis`            | `redis`                                       | Redis server host.                                                  |  
| `SYNC_REDIS_PORT`            | `--redisPort=6379`             | `6379`                                        | Redis server port.                                                  |  
| `SYNC_REDIS_PREFIX`          | `--redisPrefix=""`             | `""`                                          | Redis mutex prefix.                                                 |  
| `SYNC_REDIS_USERNAME`        | `--redisUsername=""`           | `""`                                          | Username for Redis authentication.                                  |  
| `SYNC_REDIS_PASSWORD`        | `--redisPassword=""`           | `""`                                          | Password for Redis authentication (leave empty if not required).    |  

[Menu](#components) &nbsp; &nbsp;  [Environment Source Configuration](#env-source)

---

### **Scheduler**

#### Description

The Scheduler component facilitates the execution of periodic tasks within a multi-tenant architecture. It ensures task isolation and fair resource utilization across tenants, allowing efficient and secure scheduling.

#### Key Features

- Executes scheduled tasks across multiple tenants.
- Supports isolated task execution for each tenant.
- Compatible with various environment sources, including Vault and directory-based configurations.

#### [Commands](#scheduler-commands)

| Command                    | Description                   |  
|----------------------------|-------------------------------|  
| `laralord scheduler:run`     | Starts the scheduler service. |  
| `laralord scheduler start` | Starts the scheduler service. |  

#### [Configuration (Options and Environment Variables)](#scheduler-configuration-options-and-environment-variables)

| Environment Variable          | Option                           | Default Value                                   | Description                                                         |  
|-------------------------------|----------------------------------|-----------------------------------------------|---------------------------------------------------------------------|  
| `SERVER_ENV_FILE`             | `--configEnvFile=/secrets/.laralord-env` | `/secrets/.laralord-env`                     | Path to the server's `.env` configuration file.                     |  
| `SERVER_LOG_LEVEL`            | `--logLevel=NOTICE`             | `NOTICE`                                      | Log level for the scheduler: DEBUG, INFO, NOTICE, WARNING, etc.     |  
| `SERVER_WATCH`                | `--watch=false`                 | `false`                                       | Enable WATCH mode for development environments.                     |  
| `SCHEDULER_WORKERS`           | `--workerNum=2`                 | `2`                                           | Number of worker processes to start. Defaults to available CPU cores.|  
| `APP_BASE_PATH`               | `--basePath=/var/www`           | `/var/www`                                    | Base path of the Laravel project.                                   |  
| `SYNC_METHOD`                 | `--synchronizer=redis`          | `redis`                                       | Worker synchronizer method: `redis` is used for mutex.              |  
| `SYNC_REDIS_HOST`             | `--redisHost=redis`             | `redis`                                       | Redis host for synchronization.                                     |  
| `SYNC_REDIS_PORT`             | `--redisPort=6379`              | `6379`                                        | Redis port for synchronization.                                     |  
| `SYNC_REDIS_PREFIX`           | `--redisPrefix=""`              | `""`                                          | Prefix for Redis mutex.                                             |  
| `SYNC_REDIS_USERNAME`         | `--redisUsername=""`            | `""`                                          | Redis authentication username.                                      |  
| `SYNC_REDIS_PASSWORD`         | `--redisPassword=""`            | `""`                                          | Redis authentication password. Leave blank if no authentication is required. |  

[Menu](#components) &nbsp; &nbsp;  [Environment Source Configuration](#env-source)

---

### <a id='env-source'></a> **Environment Source and Resolvers**

The commands `queue`, `scheduler`, and `server` support the `ENV_SOURCE` environment variable (`--envSource` option). This variable determines the source of environment variables and can be one of the following:

####    1. `ENV_SOURCE=vault`
Fetches credentials from Vault HashiCorp and supports the following configurations:

| Environment Variable         | Option                          | Default Value                                   | Description                                                         |  
|------------------------------|---------------------------------|-----------------------------------------------|---------------------------------------------------------------------|  
| `ENV_VAULT_ADDR`             | `--vaultAddr=http://vault:8200` | `http://vault:8200`                           | Address of the Vault HashiCorp server.                              |  
| `ENV_VAULT_TOKEN`            | `--vaultToken=""`              | `""`                                          | Token for Vault HashiCorp authentication.                           |  
| `ENV_VAULT_STORAGE`          | `--vaultStorage=secrets`       | `secrets`                                     | KV storage path in Vault HashiCorp.                                 |  
| `ENV_VAULT_PREFIX`           | `--vaultPrefix=""`             | `""`                                          | Key prefix for environments in Vault KV storage.                    |  
| `ENV_VAULT_KEY`              | `--vaultKey=secrets`           | `secrets`                                     | Secret key in Vault for single-mode operation.                      |  
| `ENV_VAULT_AUTH_TYPE`        | `--vaultAuthType=""`           | `""`                                          | Authentication type for Vault: `token`, `kubernetes`.               |  
| `ENV_VAULT_AUTH_ENDPOINT`    | `--vaultAuthEndpoint=kubernetes` | `kubernetes`                                | Authentication endpoint for Vault.                                   |  
| `ENV_VAULT_SA_TOKEN_PATH`    | `--vaultSaTokenPath=/var/run/secrets/kubernetes.io/serviceaccount/token` | `/var/run/secrets/kubernetes.io/serviceaccount/token` | Vault service account token path for Kubernetes.          |  
| `ENV_VAULT_AUTH_ROLE`        | `--vaultAuthRole=""`           | `""`                                          | Authentication role for Kubernetes in Vault.                        |  
| `ENV_VAULT_UPDATE_PERIOD`    | `--vaultUpdatePeriod=1`        | `1`                                           | Period in minutes to refresh secrets; `0` disables updates.         |  
| `ENV_FILE`                   | `--envFile=./.env`             | `./.env`                                      | Path to the project's `.env` file for storing updated variables.    |  
| `ENV_FILE_UPDATE`            | `--envFileUpdate=true`         | `false`                                       | Specifies whether the `.env` file should be updated on Vault secret updates. |  


#### 2. `ENV_SOURCE=dir`
Supports multi-tenant environments, particularly useful for local development. Configurations include:

| Environment Variable | Option            | Default Value | Description                                                          |  
|----------------------|-------------------|---------------|----------------------------------------------------------------------|  
| `ENV_DIR`            | `--envsDir=./.envs` | `./.envs`   | Directory containing `.env` files. Required when `ENV_SOURCE` is `dir`. |  


#### 3. `ENV_SOURCE=file`
Supports a single-tenant environment. This configuration directly references the path to a `.env` file.

| Environment Variable | Option            | Default Value | Description                                      |  
|----------------------|-------------------|---------------|------------------------------------------------|  
| `ENV_FILE`           | `--envFile=./.env` | `./.env`     | Path to the project's `.env` file.             |  

[Menu](#components)

 ---

### **Environment Helpers**

#### Description

This component provides helpers to manage tenant-specific environments.

On folder deploy/scripts are the bash scripts which lead to help manage the operations for multi-tenant environment.

- `tenant-context [command]` the command call laralord application to retrieve the environment variables list and apply
  it as environment variables for command
  ##### Usages:
  `TENANT_ID=12345 tenant-context php artisan migrate` - run the migration for Tenant with ID 23432432423
  > **WARNING** don't use the direct environment interpolation on the command - because it will lead to wrong result
  > 
  > `TENANT_ID=12345 tenant-context echo $APP_NAME` - will return empty line because the $APP_NAME env variable doesn't exists on the context for command call
  > 
  >  ``TENANT_ID=12345 tenant-context eval 'echo $APP_NAME'`` - will return the tenant's $APP_NAME from env variables
  >

- `tenants-iterate [command]` - iterating the command for each tenant, one by one.
  ##### Usages:
  `TENANT_ID=12345 tenants-iterate php artisan migrate` - run the migration for each tenant 
  `TENANT_ID=12345 tenants-iterate tenant-bootstrap-script.sh` - run custom script for each tenant


- `laralord env:list` - list of tenant's keys for multi-tenant environment 
  ##### Usages: 
  ```sh
    $ laralord env:list
    33434234324324
    23432434435455
    32432432423432
    23432432432324
    $
    ``` 
  check tenants-iterate script as usage code base 
   
    

---

## Licensing

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.

