[Back to Menu](../README.md#documentation)<a id='top'></a>
## **Laralord Server**

#### **How it works**

The main idea is to separate the application logic and multi-tenant management logic.

The solution is to serve the Laravel application on the specific server which resolve the events by tenants and bypass the events to Laravel application on correspond enviromment.

![](../assets/concept-diagram-dark.svg#gh-dark-mode-only)
![](../assets/concept-diagram.svg#gh-light-mode-only)

This project doesn't cover the tenant's resource provisioning.

It cover the lower level of multi-tenancy architecture.

On our site you could cover all

#### **Description**

The Server component powers the core functionality of Laralord, dynamically resolving tenant environments and serving
requests in both single and multi-tenant modes.



#### **Key Features**

- Single-tenant mode with application pre-warming for faster response times.
- Multi-tenant mode with tenant-specific environment resolution.
- Dynamic environment variable management.
- Isolated request handling using the `pcntl` extension.


#### **Commands**

| Command                     | Description                                       |  
|-----------------------------|---------------------------------------------------|  
| `laralord server:start`     | Starts the server in single or multi-tenant mode. |  
| `laralord server env:store` | Stores environment variables for a tenant.        |  
| `laralord server help`      | Displays help for the server commands.            |  
| `laralord server version`   | Prints the server version.                        |  


#### **Configuration**

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


[Menu](../README.md#documentation) &nbsp; &nbsp;  [Top](#top)
