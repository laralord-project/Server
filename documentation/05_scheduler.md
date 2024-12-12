[Back to Menu](../README.md#documentation)<a id='top'></a>

### **Scheduler**

#### **Description**

The Scheduler component facilitates the execution of periodic tasks within a multi-tenant architecture. It ensures task isolation and fair resource utilization across tenants, allowing efficient and secure scheduling.

#### **Key Features**

- Executes scheduled tasks across multiple tenants.
- Supports isolated task execution for each tenant.
- Compatible with various environment sources, including Vault and directory-based configurations.

#### **How It works**

- On execution start scheduler load all tenants environment variables from ENV SOURCE and spawn multiple child processes.
- Each of child process wait for next minute and start `artisan scheduler:run` on random order of tenants. Each command execute of the child processes pnctl fork to isolate the application.
- Before execution of tenant's command scheduler set the mutex for that tenant which will expire on next minute
- After all tenants scheduler execution complete - waiting for next minute to repeat the calls 

> <span style="color:orange;">**WARNING**</span> 
> 
> Avoid long-running scheduled commands on your Console/Kernel.php file because it could block the scheduler and cause some tenants missing minutes
> 
> Dispatch all long-running commands to queue. 

#### **Commands**

| Command                    | Description                   |  
|----------------------------|-------------------------------|  
| `laralord scheduler:run`     | Starts the scheduler service. |  
| `laralord scheduler start` | Starts the scheduler service. |  

#### **Configuration**

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


### **Usages**


[Menu](../README.md#documentation) &nbsp; &nbsp;  [Top](#top)
