# NexusPOS — User Management & Roles System Exploration Report

> Generated: 2026-05-02  
> Purpose: Understand user CRUD, roles/permissions, modules system for potential attendance/HR module extension

---

## 1. User Model

**File:** [`app/Models/User.php`](app/Models/User.php)

```php
class User extends Authenticatable
{
    protected $table = 'nexopos_users';

    protected $fillable = [
        'email', 'password', 'role_id', 'active', 'username', 'author_id',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];
}
```

### Key Relationships

| Relationship     | Method                    | Type             | Pivot/Key                                 |
| ---------------- | ------------------------- | ---------------- | ----------------------------------------- |
| UserAttribute    | `attribute()`             | `HasOne`         | `user_id` → `id`                          |
| Roles            | `roles()`                 | `HasManyThrough` | Via `UserRoleRelation` (user_id, role_id) |
| Role Assignment  | `assignRole($roleName)`   | Method           | Creates `UserRoleRelation` pivot          |
| Permission Check | `allowedTo($permission)`  | Method           | Checks via `roles()` → `permissions()`    |
| Option Access    | `options($key, $default)` | Method           | Delegates to `UserOptions` service        |

---

## 2. User-Role Relationship (MANY-TO-MANY)

Users can have **multiple roles** through a pivot table.

### Architecture

```
nexopos_users  ──≪  nexopos_users_roles_relations  ≫──  nexopos_roles  ──≪  nexopos_role_permission  ≫──  nexopos_permissions
```

### UserRoleRelation (Pivot)

**File:** [`app/Models/UserRoleRelation.php`](app/Models/UserRoleRelation.php)

```php
class UserRoleRelation extends Model
{
    protected $table = 'nexopos_users_roles_relations';

    // columns: user_id, role_id

    public function scopeCombinaison($query, $user, $role) {
        return $query->where('user_id', $user->id)->where('role_id', $role->id);
    }
}
```

### UsersService::setUserRole()

```php
public function setUserRole(User $user, $roles) {
    UserRoleRelation::where('user_id', $user->id)->delete();

    $roles = collect($roles)->unique()->toArray();

    foreach ($roles as $roleId) {
        $relation = new UserRoleRelation;
        $relation->user_id = $user->id;
        $relation->role_id = $roleId;
        $relation->save();
    }
}
```

**Behavior:** All existing roles for the user are **deleted**, then the new set is inserted. Multiple roles per user are fully supported.

---

## 3. Role Model

**File:** [`app/Models/Role.php`](app/Models/Role.php)

```php
class Role extends NsRootModel
{
    protected $table = 'nexopos_roles';

    protected $fillable = [
        'namespace', 'name', 'description',
        'reward_system_id', 'minimal_credit_payment',
    ];
}
```

### Key Methods

| Method                                | Description                                                            |
| ------------------------------------- | ---------------------------------------------------------------------- |
| `Role::namespace($name)`              | Find role by `namespace` column                                        |
| `$role->addPermissions($permissions)` | Assign permissions (string, array, Collection, or Permission instance) |
| `$role->permissions()`                | `HasManyThrough` via `RolePermission` pivot                            |
| `$role->locked`                       | Boolean flag that prevents role deletion                               |

### Existing Roles (from `database/permissions/`)

| Namespace                     | Name        | Locked |
| ----------------------------- | ----------- | ------ |
| `admin`                       | Admin       | —      |
| `nexopos.store.administrator` | Store Admin | —      |
| `nexopos.store.cashier`       | Cashier     | —      |
| `user`                        | User        | `true` |

---

## 4. Permission Model

**File:** [`app/Models/Permission.php`](app/Models/Permission.php)

```php
class Permission extends Model
{
    protected $table = 'nexopos_permissions';
    protected $fillable = ['namespace', 'name', 'description'];
}
```

### Key Methods

| Method                                  | Description                                             |
| --------------------------------------- | ------------------------------------------------------- |
| `Permission::namespace($name)`          | Find permission by namespace                            |
| `Permission::withNamespaceOrNew($name)` | Find or create new unsaved instance                     |
| `$perm->roles()`                        | `HasManyThrough` via `RolePermission` pivot             |
| `$perm->removeFromRoles()`              | Delete all `RolePermission` entries for this permission |

### RolePermission (Pivot)

**File:** [`app/Models/RolePermission.php`](app/Models/RolePermission.php)

- Table: `nexopos_role_permission`
- Columns: `role_id`, `permission_id`
- Non-incrementing, non-timestamped

---

## 5. Permission Naming Convention

All permissions follow this pattern:

```
{domain}.{action}
```

Example attendance permissions:

| Namespace            | Name                      |
| -------------------- | ------------------------- |
| `attendance.clock`   | Clock In/Out              |
| `attendance.read`    | View Attendance Records   |
| `attendance.create`  | Create Attendance Records |
| `attendance.update`  | Update Attendance Records |
| `attendance.delete`  | Delete Attendance Records |
| `attendance.reports` | View Attendance Reports   |

### Permission Assignment by Role

Permissions are defined in files under [`database/permissions/`](database/permissions/).  
Each file creates Permission records and assigns them to roles.

**Attendance assignments:**

- **admin**: All 6 (clock, read, create, update, delete, reports)
- **store admin**: 5 (clock, read, create, update, reports)
- **cashier**: 1 (clock only)

---

## 6. User CRUD (Admin UI)

**File:** [`app/Crud/UserCrud.php`](app/Crud/UserCrud.php)

Extends `CrudService`. Defines form fields for creating/editing users.

### CRUD Form Fields (`getForm()`)

#### Main Section

| Field    | Type   | Name       | Validation                                |
| -------- | ------ | ---------- | ----------------------------------------- |
| Username | `text` | `username` | `required\|unique:nexopos_users,username` |

#### Tabs

**General Tab:**
| Field | Type | Name | Validation |
|---|---|---|---|
| Email | `text` | `email` | `required\|email\|unique:nexopos_users,email` |
| Password | `password` | `password` | `sometimes\|min:6` |
| Confirm Password | `password` | `password_confirm` | `sometimes\|same:general.password` |
| Active | `switch` | `active` | — |
| Roles | `multiselect` | `roles` | (uses `Role::get()` for options) |

**Billing Tab:**
| Field | Type | Name |
|---|---|---|
| First Name | `text` | `first_name` |
| Last Name | `text` | `last_name` |
| Phone | `text` | `phone` |
| Email | `text` | `email` |
| Address Line 1 | `text` | `address_1` |
| Address Line 2 | `text` | `address_2` |
| Country | `text` | `country` |
| State | `text` | `state` |
| City | `text` | `city` |
| Zip Code | `text` | `zipcode` |

**Shipping Tab:**
| Field | Type | Name |
|---|---|---|
| (Same 10 fields as Billing) | `text` | (prefixed with shipping context) |

### Table Columns (`getColumns()`)

- `username` (with `active` and `email` attributes)
- `account_amount` (Wallet)
- `owed_amount` (Owed)
- `purchases_amount` (Purchases)
- `roles` (via `$entry->rolesNames`)

### Actions

- **Edit** (`update.users` permission)
- **Orders**
- **Rewards**
- **Delete** (with confirmation, prevents self-deletion)

### Post-Create/Update Hooks

- `afterPost()` → Calls `$this->userService->setUserRole($entry, $request['roles'])` and `$this->userService->createAttribute($entry)`

---

## 7. Web & API Routes

### Web Routes (`routes/web/users.php`)

| Route                                  | Controller          | Name                              |
| -------------------------------------- | ------------------- | --------------------------------- |
| `GET /users`                           | `listUsers`         | `ns.dashboard.users`              |
| `GET /users/create`                    | `createUser`        | `ns.dashboard.users-create`       |
| `GET /users/edit/{user}`               | `editUser`          | `ns.dashboard.users.edit`         |
| `GET /users/roles`                     | `rolesList`         | `ns.dashboard.users.roles`        |
| `GET /users/roles/create`              | `createRole`        | `ns.dashboard.users.roles-create` |
| `GET /users/roles/edit/{role}`         | `editRole`          | `ns.dashboard.users.roles-edit`   |
| `GET /users/roles/permissions-manager` | `permissionManager` | —                                 |
| `GET /users/profile`                   | `getProfile`        | `ns.dashboard.users.profile`      |

### API Routes (`routes/api/users.php`)

**Current-user routes (no middleware):**
| Method | Route | Purpose |
|---|---|---|
| `GET` | `/api/user` | Get current user |
| `GET` | `/api/user/permissions` | Get user permissions |
| `POST` | `/api/user/access/{id}` | Approve permission access |
| `GET` | `/api/user/access/{id}` | Get access status |
| `GET` | `/api/user/access/{access}/use` | Mark access as used |
| `POST` | `/api/users/widgets` | Configure dashboard widgets |
| `POST` | `/api/users/create-token` | Create Sanctum token |
| `GET` | `/api/users/tokens` | List tokens |
| `DELETE` | `/api/users/tokens/{id}` | Delete token |
| `POST` | `/api/users/check-permission` | Check a permission |

**Restricted routes (`read.users` middleware):**
| Method | Route | Purpose |
|---|---|---|
| `GET` | `/api/users` | List users |
| `GET` | `/api/users/permissions` | Get all permissions |

**Restricted routes (`read.roles` / `update.roles` / `create.roles` middleware):**
| Method | Route | Purpose |
|---|---|---|
| `GET` | `/api/users/roles` | List roles |
| `PUT` | `/api/users/roles` | Update role |
| `GET` | `/api/users/roles/{role}/clone` | Clone role |

---

## 8. User Meta / Settings / Options System

### UserAttribute Model

**File:** [`app/Models/UserAttribute.php`](app/Models/UserAttribute.php)

| Column        | Type    | Comment                  |
| ------------- | ------- | ------------------------ |
| `user_id`     | integer | FK to User               |
| `avatar_link` | string  | Profile picture URL      |
| `theme`       | string  | Dashboard theme          |
| `language`    | string  | User language preference |

- Created automatically via `UsersService::createAttribute()` on user creation
- One-to-one relationship: `User::attribute()` → `UserAttribute`

### UserOptions Service

**File:** [`app/Services/UserOptions.php`](app/Services/UserOptions.php)

Extends base `Options` class, stores arbitrary key-value pairs per user in the `options` table:

```php
class UserOptions extends Options
{
    public function set($key, $value, $expiration = null);  // Create or update
    // Inherited: get($key, $default)
}
```

Options table columns: `key`, `value`, `user_id`, `expire_on`, `array`

**Usage in User model:**

```php
$user->options($option, $default);  // Quick access via UserOptions service
```

---

## 9. User Profile Form

**File:** [`app/Forms/UserProfileForm.php`](app/Forms/UserProfileForm.php)

Extends `SettingsPage` with tabs:

| Tab       | Form File                                                                      | Fields                              |
| --------- | ------------------------------------------------------------------------------ | ----------------------------------- |
| Attribute | [`app/Forms/user-profile/attribute.php`](app/Forms/user-profile/attribute.php) | Language, Avatar (link), Theme      |
| Billing   | [`app/Forms/user-profile/billing.php`](app/Forms/user-profile/billing.php)     | Address fields                      |
| Shipping  | [`app/Forms/user-profile/shipping.php`](app/Forms/user-profile/shipping.php)   | Address fields                      |
| Security  | [`app/Forms/user-profile/security.php`](app/Forms/user-profile/security.php)   | Old password, new password, confirm |
| Token     | [`app/Forms/user-profile/token.php`](app/Forms/user-profile/token.php)         | API token management                |

---

## 10. Modules Directory Structure

**Directory:** [`modules/`](modules/) — **Currently empty (no installed modules).**

The module system is comprehensive, with these generator commands available in [`app/Console/Commands/`](app/Console/Commands/):

| Command                                                                             | Purpose                        |
| ----------------------------------------------------------------------------------- | ------------------------------ |
| `GenerateModuleCommand`                                                             | Create new module scaffold     |
| `ModuleController`                                                                  | Generate module controller     |
| `ModuleModels`                                                                      | Generate module models         |
| `ModuleMigrations`                                                                  | Generate module migrations     |
| `ModuleSettings`                                                                    | Generate module settings pages |
| `ModuleEvent`, `ModuleJob`, `ModuleListerner`, `ModuleMailCommand`, `ModuleRequest` | Generate module components     |
| `ModuleEnableCommand` / `ModuleDisableCommand`                                      | Enable/disable modules         |
| `ModuleDeleteCommand`                                                               | Delete a module                |
| `ModulesMigrateCommand`                                                             | Run module migrations          |
| `ModuleSymlinkCommand`                                                              | Symlink module assets          |

Coupled with [`app/Providers/ModulesServiceProvider.php`](app/Providers/ModulesServiceProvider.php) which auto-discovers modules.

---

## 11. Attendance Module — Current State

### Existing Files

| File                                                                         | Purpose                                   |
| ---------------------------------------------------------------------------- | ----------------------------------------- |
| [`routes/api/attendance.php`](routes/api/attendance.php)                     | Attendance API routes                     |
| [`routes/web/attendance.php`](routes/web/attendance.php)                     | Attendance web pages                      |
| [`database/permissions/attendance.php`](database/permissions/attendance.php) | Permission definitions & role assignments |

### Attendance Web Routes

| Route                               | Controller Method  | Name                             |
| ----------------------------------- | ------------------ | -------------------------------- |
| `GET /attendance`                   | `listAttendances`  | `ns.dashboard.attendance`        |
| `GET /attendance/create`            | `createAttendance` | `ns.dashboard.attendance-create` |
| `GET /attendance/edit/{attendance}` | `editAttendance`   | `ns.dashboard.attendance-edit`   |
| `GET /attendance/clock`             | `clockPage`        | `ns.dashboard.attendance-clock`  |

### Additional Observations

- **No existing module** extends user functionality — the modules directory is empty
- The attendance system is built **directly into the core**, not as a module
- There is no `AttendanceCrud` file in [`app/Crud/`](app/Crud/) — attendance CRUD appears to use a different pattern
- The `AttendanceController` exists at [`app/Http/Controllers/Dashboard/AttendanceController.php`](app/Http/Controllers/Dashboard/AttendanceController.php)

---

## 12. CrudService — Form Field Types Supported

Based on the `UserCrud` implementation, the CrudService `getForm()` returns this structure:

```php
[
    'main' => [
        'label' => '...',
        'name'  => 'field_name',
        'value' => $entry->field_name ?? '',
        'validation' => '...',
        'description' => '...',
    ],
    'tabs' => [
        'tab_key' => [
            'label'  => 'Tab Label',
            'fields' => [
                [
                    'type'        => 'text',           // text, password, switch, multiselect
                    'name'        => 'field_name',
                    'label'       => 'Field Label',
                    'value'       => '...',
                    'validation'  => '...',
                    'description' => '...',
                    'options'     => [...],             // For select/multiselect fields
                ],
            ],
        ],
    ],
];
```

**Known field types:** `text`, `password`, `switch`, `multiselect`

For select/dropdown options, the helper `Helper::toJsOptions(Collection, [value_column, label_column])` or `Helper::kvToJsOptions(array)` is used.

---

## 13. Key Architectural Patterns

### NsRestrictMiddleware

Permission-checking middleware used like:

```php
->middleware(NsRestrictMiddleware::arguments('read.users'))
```

This checks if the authenticated user's roles have the `read.users` permission.

### CRUD Namespace Registration

CRUD services are registered via [`app/Providers/CrudServiceProvider.php`](app/Providers/CrudServiceProvider.php) using a namespace like `ns.users`.

### Events/Hooks

The `eventy` package (TorMorten/Eventy) is used for hooks. Example:

```php
Hook::action('some.action', $params);  // Action hook (do)
Hook::filter('some.filter', $value, $params);  // Filter hook (modify)
```

Used in UserCrud for extensibility (e.g., `afterPost` calls `Hook::action('user.after.created', ...)`).

---

## 14. Summary for Module Extension

| Question                     | Answer                                                                                    |
| ---------------------------- | ----------------------------------------------------------------------------------------- |
| User-Role relationship type  | **Many-to-many** via `UserRoleRelation` pivot                                             |
| User fillable fields         | `email`, `password`, `role_id`, `active`, `username`, `author_id`                         |
| User hidden fields           | `password`, `remember_token`                                                              |
| User meta/options mechanism  | `UserOptions` service (key-value in `options` table) + `UserAttribute` model (structured) |
| Roles system                 | `nexopos_roles` → `RolePermission` → `nexopos_permissions`                                |
| Permission format            | `{domain}.{action}` (e.g., `attendance.read`)                                             |
| Modules directory            | Empty — no installed modules, but module generator commands exist                         |
| CRUD form types              | `text`, `password`, `switch`, `multiselect`                                               |
| CRUD extends                 | Extend `CrudService`, override `getForm()`, `getColumns()`, `setActions()`                |
| How to add user fields       | Add to `UserCrud::getForm()` tabs, add to `fillable`, add migration                       |
| How to add module permission | Create file in `database/permissions/`, use `Permission::firstOrNew()` pattern            |
