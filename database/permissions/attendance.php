<?php

use App\Models\Permission;
use App\Models\Role;

/**
 * Always create permission records if they don't exist
 */
$clockPerm = Permission::firstOrNew( [ 'namespace' => 'attendance.clock' ] );
$clockPerm->name = __( 'Clock In/Out' );
$clockPerm->namespace = 'attendance.clock';
$clockPerm->description = __( 'Allows the user to clock in and clock out.' );
$clockPerm->save();

$readPerm = Permission::firstOrNew( [ 'namespace' => 'attendance.read' ] );
$readPerm->name = __( 'View Attendance Records' );
$readPerm->namespace = 'attendance.read';
$readPerm->description = __( 'Allows the user to view attendance records.' );
$readPerm->save();

$createPerm = Permission::firstOrNew( [ 'namespace' => 'attendance.create' ] );
$createPerm->name = __( 'Create Attendance Records' );
$createPerm->namespace = 'attendance.create';
$createPerm->description = __( 'Allows the user to create attendance records.' );
$createPerm->save();

$updatePerm = Permission::firstOrNew( [ 'namespace' => 'attendance.update' ] );
$updatePerm->name = __( 'Update Attendance Records' );
$updatePerm->namespace = 'attendance.update';
$updatePerm->description = __( 'Allows the user to update attendance records.' );
$updatePerm->save();

$deletePerm = Permission::firstOrNew( [ 'namespace' => 'attendance.delete' ] );
$deletePerm->name = __( 'Delete Attendance Records' );
$deletePerm->namespace = 'attendance.delete';
$deletePerm->description = __( 'Allows the user to delete attendance records.' );
$deletePerm->save();

$reportsPerm = Permission::firstOrNew( [ 'namespace' => 'attendance.reports' ] );
$reportsPerm->name = __( 'View Attendance Reports' );
$reportsPerm->namespace = 'attendance.reports';
$reportsPerm->description = __( 'Allows the user to view attendance reports.' );
$reportsPerm->save();

/**
 * Assign permissions to roles
 */
$admin = Role::namespace( 'admin' );
$admin->addPermissions( [
    'attendance.clock',
    'attendance.read',
    'attendance.create',
    'attendance.update',
    'attendance.delete',
    'attendance.reports',
] );

$storeAdmin = Role::namespace( 'nexopos.store.administrator' );
$storeAdmin->addPermissions( [
    'attendance.clock',
    'attendance.read',
    'attendance.create',
    'attendance.update',
    'attendance.reports',
] );

$cashier = Role::namespace( 'nexopos.store.cashier' );
$cashier->addPermissions( [
    'attendance.clock',
    'attendance.read',
] );
