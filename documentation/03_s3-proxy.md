[Back to Menu](../README.md#documentation)<a id='top'></a>

### **S3-Proxy**

#### **Description**

The S3-Proxy component provides a seamless way to proxy requests to S3 or S3-compatible storage backends. It has no sense as independent service, but could be very helpful in pair with gateway service

#### **Key Features**

- Transparent proxying of requests to S3 backends.
- Coroutine enabled for S3 client. Non-blocking requests processing.
- Support for tenant-specific configurations in multi-tenant mode.
- Serve any number of static sites from S3 using only one server


#### **How it works**

The main idea is to provide access to s3 with Frontend static files - and allow externally manage the access to this bucket.
Each request to s3 proxy should contains the header 'DOCUMENT-ROOT' which point to the specific folder(key) on S3 bucket, and file resolving by location on request.
> **WARNING** All list requests are respond with file {DOCUMENT-ROOT}/index.html


#### **Commands**

| Command                     | Description                          |  
|-----------------------------|--------------------------------------|  
| `laralord s3-proxy:start`   | Starts the S3 proxy server.          |  
| `laralord s3-proxy help`    | Displays help for S3 proxy commands. |  
| `laralord s3-proxy version` | Prints the S3 proxy version.         |  

#### **Configuration**

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


[Menu](../README.md#documentation) &nbsp; &nbsp;  [Top](#top)
