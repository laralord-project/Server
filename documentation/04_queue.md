[Back to Menu](../README.md#documentation)<a id='top'></a>
### **Queue**

#### **Description**

The Queue component manages worker processes for handling jobs in a multi-tenant environment, ensuring efficient and fair resource distribution across tenants.

#### **How it Works...**

The queue's main process loads the environment variables for each tenant and spawns child processes. The number of workers is specified by configuration.  
Each worker processes a set of tenants and executes `php assetsisan queue:work --once ...` within the context of each tenant.

**Key Mechanics:**
- Workers ensure fair resource allocation using Redis-based mutex locks.
- If a mutex lock fails, indicating a tenant already has an assigned worker, the tenant's execution is skipped, and the process moves to the next tenant.
- Worker processes fetch tenant-specific environment variables from the main process's shared memory.

The main process periodically refreshes tenants' environment configurations to ensure updates are reflected in the system.

#### **Key Features**

- Unified multi-tenant queue processing.
- Supports task-specific isolation.
- Tenants can use different queue drivers.
- Dynamic synchronization through Redis mutex.

#### **Commands**

| Command                | Description            |  
|------------------------|------------------------|  
| `laralord queue:work`  | Start queue workers.   |  
| `laralord queue start` | Alias for `queue:work`. |  

#### **Configuration**

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

#### **Usages**

[Menu](../README.md#documentation) &nbsp; &nbsp;  [Top](#top)
