# Database Schema Reference (manual setup)

Este proyecto asume que crearas manualmente las tablas y restricciones.

## Tablas esperadas

### users
- `id` BIGINT PK AI
- `username` VARCHAR(60) UNIQUE
- `email` VARCHAR(190) UNIQUE
- `password_hash` VARCHAR(255)
- `is_active` TINYINT(1)
- `created_at` DATETIME
- `updated_at` DATETIME
- `last_login_at` DATETIME NULL

### roles
- `id` BIGINT PK AI
- `name` VARCHAR(80) UNIQUE
- `description` VARCHAR(255)
- `is_active` TINYINT(1)
- `created_at` DATETIME
- `updated_at` DATETIME

### permissions
- `id` BIGINT PK AI
- `name` VARCHAR(80) UNIQUE (ej: `users.read`)
- `description` VARCHAR(255)
- `is_active` TINYINT(1)
- `created_at` DATETIME
- `updated_at` DATETIME

Permisos minimos recomendados para esta v1:
- `users.read`
- `users.create`
- `users.update`
- `users.roles.assign`
- `roles.read`
- `roles.create`
- `roles.update`
- `roles.permissions.assign`
- `permissions.read`

### user_roles
- `user_id` BIGINT FK -> users.id
- `role_id` BIGINT FK -> roles.id
- `created_at` DATETIME
- PK compuesta (`user_id`, `role_id`)

### role_permissions
- `role_id` BIGINT FK -> roles.id
- `permission_id` BIGINT FK -> permissions.id
- `created_at` DATETIME
- PK compuesta (`role_id`, `permission_id`)

### api_sessions
- `id` VARCHAR(128) PK
- `payload` MEDIUMTEXT
- `last_activity` INT
- `user_id` BIGINT NULL
- `ip_address` VARCHAR(45)
- `user_agent` VARCHAR(255)
- `created_at` DATETIME
- `updated_at` DATETIME
- `revoked_at` DATETIME NULL

### login_attempts
- `id` BIGINT PK AI
- `identifier` VARCHAR(190)
- `ip_address` VARCHAR(45)
- `successful` TINYINT(1)
- `attempted_at` INT
- `user_id` BIGINT NULL
- `reason` VARCHAR(120)

Indices sugeridos:
- (`identifier`, `ip_address`, `attempted_at`)
- (`identifier`, `ip_address`, `successful`, `attempted_at`)

### audit_logs
- `id` BIGINT PK AI
- `user_id` BIGINT NULL
- `event` VARCHAR(80)
- `resource` VARCHAR(80)
- `action` VARCHAR(80)
- `ip_address` VARCHAR(45)
- `user_agent` VARCHAR(255)
- `meta_json` JSON o TEXT
- `created_at` DATETIME

Indices sugeridos:
- (`user_id`, `created_at`)
- (`event`, `created_at`)
