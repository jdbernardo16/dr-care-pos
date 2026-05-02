<?php

use App\Models\Permission;
use App\Models\Role;

// --- Overtime permissions ---
$perm = Permission::firstOrNew( [ 'namespace' => 'overtime.create' ] );
$perm->name = __( 'File Overtime' );
$perm->namespace = 'overtime.create';
$perm->description = __( 'Allows filing overtime requests.' );
$perm->save();

$perm = Permission::firstOrNew( [ 'namespace' => 'overtime.read' ] );
$perm->name = __( 'View Overtime Requests' );
$perm->namespace = 'overtime.read';
$perm->description = __( 'Allows viewing overtime requests.' );
$perm->save();

$perm = Permission::firstOrNew( [ 'namespace' => 'overtime.approve' ] );
$perm->name = __( 'Approve Overtime' );
$perm->namespace = 'overtime.approve';
$perm->description = __( 'Allows approving or rejecting overtime requests.' );
$perm->save();

// --- Holiday permissions ---
$perm = Permission::firstOrNew( [ 'namespace' => 'holiday.create' ] );
$perm->name = __( 'Create Holidays' );
$perm->namespace = 'holiday.create';
$perm->description = __( 'Allows creating holiday entries.' );
$perm->save();

$perm = Permission::firstOrNew( [ 'namespace' => 'holiday.read' ] );
$perm->name = __( 'View Holidays' );
$perm->namespace = 'holiday.read';
$perm->description = __( 'Allows viewing holiday entries.' );
$perm->save();

$perm = Permission::firstOrNew( [ 'namespace' => 'holiday.update' ] );
$perm->name = __( 'Update Holidays' );
$perm->namespace = 'holiday.update';
$perm->description = __( 'Allows updating holiday entries.' );
$perm->save();

$perm = Permission::firstOrNew( [ 'namespace' => 'holiday.delete' ] );
$perm->name = __( 'Delete Holidays' );
$perm->namespace = 'holiday.delete';
$perm->description = __( 'Allows deleting holiday entries.' );
$perm->save();

// --- Assign to admin ---
$admin = Role::namespace( 'admin' );
$admin->addPermissions( [
    'overtime.create',
    'overtime.read',
    'overtime.approve',
    'holiday.create',
    'holiday.read',
    'holiday.update',
    'holiday.delete',
] );

// --- Store admin gets read + create (self-file) ---
$storeAdmin = Role::namespace( 'nexopos.store.administrator' );
$storeAdmin->addPermissions( [
    'overtime.read',
    'overtime.approve',
    'holiday.read',
] );

// --- Cashier can file overtime ---
$cashier = Role::namespace( 'nexopos.store.cashier' );
$cashier->addPermissions( [
    'overtime.create',
] );
