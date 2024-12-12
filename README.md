
[![Test](https://github.com/laralord-project/server/actions/workflows/test.yaml/badge.svg?branch=main)](https://github.com/laralord-project/server/actions/workflows/test.yaml)


# <img src="assets/logo.png" width="28" alt="LaraLord Server" >  &nbsp; Laralord Project - Server

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

## **Documentation**

1. **[Installation](documentation/01_installation.md)**
    - [Composer](documentation/01_installation.md#composer-install) 
    - [Download Binary File](documentation/01_installation.md#download-binary-file)
    - [Docker Image](documentation/01_installation.md#docker-image)
2. **[Server](documentation/02_server.md)**
    - [How it works...](documentation/02_server.md#how-it-works)
    - [Commands](documentation/02_server.md#commands)
    - [Configuration (Options and Environment Variables)](documentation/02_server.md#configuration)
3. **[S3-Proxy](documentation/03_s3-proxy.md)
    - [How it works...](documentation/03_s3-proxy.md#how-it-works)
    - [Commands](#commands)
    - [Configuration (Options and Environment Variables)](#s3-proxy-configuration-options-and-environment-variables)
4. **[Queue](#queue)**
    - [Commands](#queue-commands)
    - [Configuration (Options and Environment Variables)](#queue-configuration-options-and-environment-variables)
5. **[Scheduler](#scheduler)**
    - [Commands](#scheduler-commands)
    - [Configuration (Options and Environment Variables)](#scheduler-configuration-options-and-environment-variables)
6. **[Environment Source and Resolvers](#env-source)**
7. **[Bash Helpers](#environment-helpers)**

---

## Licensing

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

