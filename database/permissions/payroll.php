<?php

use App\Models\Permission;
use App\Models\Role;

$readPerm = Permission::firstOrNew( [ 'namespace' => 'payroll.read' ] );
$readPerm->name = __( 'View Payroll Runs' );
$readPerm->namespace = 'payroll.read';
$readPerm->description = __( 'Allows the user to view payroll runs and payslips.' );
$readPerm->save();

$createPerm = Permission::firstOrNew( [ 'namespace' => 'payroll.create' ] );
$createPerm->name = __( 'Create Payroll Drafts' );
$createPerm->namespace = 'payroll.create';
$createPerm->description = __( 'Allows the user to create draft payroll runs.' );
$createPerm->save();

$postPerm = Permission::firstOrNew( [ 'namespace' => 'payroll.post' ] );
$postPerm->name = __( 'Post Payroll Runs' );
$postPerm->namespace = 'payroll.post';
$postPerm->description = __( 'Allows the user to post payroll runs (creates accounting entries).' );
$postPerm->save();

$voidPerm = Permission::firstOrNew( [ 'namespace' => 'payroll.void' ] );
$voidPerm->name = __( 'Void Payroll Runs' );
$voidPerm->namespace = 'payroll.void';
$voidPerm->description = __( 'Allows the user to void posted payroll runs.' );
$voidPerm->save();

$deletePerm = Permission::firstOrNew( [ 'namespace' => 'payroll.delete' ] );
$deletePerm->name = __( 'Delete Payroll Drafts' );
$deletePerm->namespace = 'payroll.delete';
$deletePerm->description = __( 'Allows the user to delete draft payroll runs.' );
$deletePerm->save();

$setRatePerm = Permission::firstOrNew( [ 'namespace' => 'payroll.set-rate' ] );
$setRatePerm->name = __( 'Set Hourly Rate' );
$setRatePerm->namespace = 'payroll.set-rate';
$setRatePerm->description = __( 'Allows the user to set hourly rates on user profiles.' );
$setRatePerm->save();

$reportsPerm = Permission::firstOrNew( [ 'namespace' => 'payroll.reports' ] );
$reportsPerm->name = __( 'View Payroll Reports' );
$reportsPerm->namespace = 'payroll.reports';
$reportsPerm->description = __( 'Allows the user to view payroll summary reports.' );
$reportsPerm->save();

$admin = Role::namespace( 'admin' );
$admin->addPermissions( [
    'payroll.read',
    'payroll.create',
    'payroll.post',
    'payroll.void',
    'payroll.delete',
    'payroll.set-rate',
    'payroll.reports',
] );

$storeAdmin = Role::namespace( 'nexopos.store.administrator' );
$storeAdmin->addPermissions( [
    'payroll.read',
    'payroll.create',
    'payroll.reports',
] );
