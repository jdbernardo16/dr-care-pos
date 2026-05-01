<?php

namespace App\Crud;

use App\Exceptions\NotAllowedException;
use App\Models\Attendance;
use App\Models\User;
use App\Services\CrudEntry;
use App\Services\CrudService;
use App\Services\Helper;
use Illuminate\Http\Request;
use TorMorten\Eventy\Facades\Events as Hook;

class AttendanceCrud extends CrudService
{
    const AUTOLOAD = true;
    const IDENTIFIER = 'ns.attendance';

    protected $table = 'nexopos_attendance';
    protected $slug = 'attendance';
    protected $namespace = 'ns.attendance';
    protected $model = Attendance::class;

    protected $permissions = [
        'create' => 'attendance.create',
        'read' => 'attendance.read',
        'update' => 'attendance.update',
        'delete' => 'attendance.delete',
    ];

    public $relations = [
        'join' => [
            [ User::class, 'user' ],
        ],
        'leftJoin' => [
            [ User::class, 'author' ],
        ],
    ];

    public $pick = [
        'user' => [ 'username' ],
        'author' => [ 'username' ],
    ];

    protected $listWhere = [];
    protected $whereIn = [];
    public $fillable = [];

    public function __construct()
    {
        parent::__construct();
    }

    public function getLabels()
    {
        return [
            'list_title' => __( 'Attendance Records' ),
            'list_description' => __( 'Display all attendance records.' ),
            'no_entry' => __( 'No attendance records found.' ),
            'create_new' => __( 'Add a new attendance record' ),
            'create_title' => __( 'Create a new attendance record' ),
            'create_description' => __( 'Record an attendance entry manually.' ),
            'edit_title' => __( 'Edit attendance record' ),
            'edit_description' => __( 'Modify an attendance record.' ),
            'back_to_list' => __( 'Return to Attendance Records' ),
        ];
    }

    public function getForm( $entry = null )
    {
        return [
            'main' => [
                'label' => __( 'Employee' ),
                'name' => 'user_id',
                'value' => $entry->user_id ?? '',
                'description' => __( 'The employee associated with this attendance record.' ),
                'validation' => 'required',
            ],
            'tabs' => [
                'general' => [
                    'label' => __( 'General' ),
                    'fields' => [
                        [
                            'type' => 'select',
                            'name' => 'user_id',
                            'label' => __( 'Employee' ),
                            'options' => Helper::toJsOptions( User::all(), [ 'id', 'username' ] ),
                            'value' => $entry->user_id ?? '',
                            'description' => __( 'Select the employee.' ),
                            'validation' => 'required',
                        ],
                        [
                            'type' => 'datetime',
                            'name' => 'clock_in_at',
                            'label' => __( 'Clock In' ),
                            'value' => $entry->clock_in_at ?? '',
                            'description' => __( 'Date and time the employee clocked in.' ),
                            'validation' => 'required',
                        ],
                        [
                            'type' => 'datetime',
                            'name' => 'clock_out_at',
                            'label' => __( 'Clock Out' ),
                            'value' => $entry->clock_out_at ?? '',
                            'description' => __( 'Date and time the employee clocked out (optional if still active).' ),
                        ],
                        [
                            'type' => 'number',
                            'name' => 'total_hours',
                            'label' => __( 'Total Hours' ),
                            'value' => $entry->total_hours ?? '',
                            'description' => __( 'Calculated total hours (auto-calculated on clock-out).' ),
                            'attributes' => [
                                'step' => '0.01',
                            ],
                        ],
                        [
                            'type' => 'select',
                            'name' => 'status',
                            'label' => __( 'Status' ),
                            'options' => Helper::kvToJsOptions( [
                                Attendance::STATUS_CLOCKED_IN => __( 'Clocked In' ),
                                Attendance::STATUS_CLOCKED_OUT => __( 'Clocked Out' ),
                                Attendance::STATUS_ABSENT => __( 'Absent' ),
                                Attendance::STATUS_ON_BREAK => __( 'On Break' ),
                            ] ),
                            'value' => $entry->status ?? Attendance::STATUS_CLOCKED_IN,
                            'description' => __( 'Current status of this attendance record.' ),
                            'validation' => 'required',
                        ],
                        [
                            'type' => 'textarea',
                            'name' => 'clock_in_note',
                            'label' => __( 'Clock In Note' ),
                            'value' => $entry->clock_in_note ?? '',
                            'description' => __( 'Any note about the clock-in.' ),
                        ],
                        [
                            'type' => 'textarea',
                            'name' => 'clock_out_note',
                            'label' => __( 'Clock Out Note' ),
                            'value' => $entry->clock_out_note ?? '',
                            'description' => __( 'Any note about the clock-out.' ),
                        ],
                    ],
                ],
            ],
        ];
    }

    public function getColumns(): array
    {
        return [
            'user_username' => [
                'label' => __( 'Employee' ),
                '$direction' => '',
                '$sort' => false,
            ],
            'clock_in_at' => [
                'label' => __( 'Clock In' ),
                '$direction' => '',
                '$sort' => false,
            ],
            'clock_out_at' => [
                'label' => __( 'Clock Out' ),
                '$direction' => '',
                '$sort' => false,
            ],
            'total_hours' => [
                'label' => __( 'Hours' ),
                '$direction' => '',
                '$sort' => false,
            ],
            'status' => [
                'label' => __( 'Status' ),
                '$direction' => '',
                '$sort' => false,
            ],
            'author_username' => [
                'label' => __( 'Recorded By' ),
                '$direction' => '',
                '$sort' => false,
            ],
            'created_at' => [
                'label' => __( 'Created At' ),
                '$direction' => '',
                '$sort' => false,
            ],
        ];
    }

    public function setActions( CrudEntry $entry ): CrudEntry
    {
        $entry->clock_in_note = $entry->clock_in_note ?: __( 'N/A' );
        $entry->clock_out_note = $entry->clock_out_note ?: __( 'N/A' );
        $entry->total_hours = $entry->total_hours ?: __( 'N/A' );

        $entry->action(
            identifier: 'edit',
            label: __( 'Edit' ),
            type: 'GOTO',
            url: ns()->url( '/dashboard/' . 'attendance' . '/edit/' . $entry->id )
        );

        $entry->action(
            identifier: 'delete',
            label: __( 'Delete' ),
            type: 'DELETE',
            url: ns()->url( '/api/crud/' . self::IDENTIFIER . '/' . $entry->id ),
            confirm: [
                'message' => __( 'Would you like to delete this attendance record?' ),
            ]
        );

        return $entry;
    }

    public function getLinks(): array
    {
        return [
            'list' => ns()->url( 'dashboard/' . 'attendance' ),
            'create' => ns()->url( 'dashboard/' . 'attendance/create' ),
            'edit' => ns()->url( 'dashboard/' . 'attendance/edit/{id}' ),
            'post' => ns()->url( 'api/crud/' . self::IDENTIFIER ),
            'put' => ns()->url( 'api/crud/' . self::IDENTIFIER . '/{id}' . '' ),
        ];
    }

    public function getBulkActions(): array
    {
        return Hook::filter( $this->namespace . '-bulk', [
            [
                'label' => __( 'Delete Selected Records' ),
                'identifier' => 'delete_selected',
                'url' => ns()->route( 'ns.api.crud-bulk-actions', [
                    'namespace' => $this->namespace,
                ] ),
            ],
        ] );
    }

    public function bulkAction( Request $request )
    {
        if ( $request->input( 'action' ) == 'delete_selected' ) {
            if ( $this->permissions[ 'delete' ] !== false ) {
                ns()->restrict( $this->permissions[ 'delete' ] );
            } else {
                throw new NotAllowedException;
            }

            $status = [
                'success' => 0,
                'error' => 0,
            ];

            foreach ( $request->input( 'entries' ) as $id ) {
                $entity = $this->model::find( $id );
                if ( $entity instanceof Attendance ) {
                    $entity->delete();
                    $status[ 'success' ]++;
                } else {
                    $status[ 'error' ]++;
                }
            }

            return $status;
        }

        return Hook::filter( $this->namespace . '-catch-action', false, $request );
    }
}
