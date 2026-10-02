<?php

namespace App\Services;

use App\Exceptions\NotAllowedException;
use App\Models\OvertimeRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class OvertimeRequestService
{
    public function fileRequest( $userId, $date, $startTime, $endTime, $reason )
    {
        $start = Carbon::parse( $startTime );
        $end = Carbon::parse( $endTime );

        if ( $end <= $start ) {
            throw new NotAllowedException( __( 'End time must be after start time.' ) );
        }

        $totalHours = round( $start->diffInMinutes( $end ) / 60, 2 );

        $existing = OvertimeRequest::where( 'user_id', $userId )
            ->whereDate( 'date', $date )
            ->whereIn( 'status', [ OvertimeRequest::STATUS_PENDING, OvertimeRequest::STATUS_APPROVED ] )
            ->exists();

        if ( $existing ) {
            throw new NotAllowedException( __( 'An overtime request already exists for this date.' ) );
        }

        $request = new OvertimeRequest;
        $request->user_id = $userId;
        $request->date = $date;
        $request->start_time = $start;
        $request->end_time = $end;
        $request->total_hours = $totalHours;
        $request->reason = $reason;
        $request->status = OvertimeRequest::STATUS_PENDING;
        $request->author_id = Auth::id();
        $request->save();

        return [
            'status' => 'success',
            'message' => __( 'Overtime request filed.' ),
            'data' => compact( 'request' ),
        ];
    }

    public function approve( $requestId, $adminNote = null )
    {
        $request = OvertimeRequest::findOrFail( $requestId );

        if ( $request->status !== OvertimeRequest::STATUS_PENDING ) {
            throw new NotAllowedException( __( 'Only pending requests can be approved.' ) );
        }

        $request->status = OvertimeRequest::STATUS_APPROVED;
        $request->approved_by = Auth::id();
        $request->approved_at = now();
        $request->admin_note = $adminNote;
        $request->save();

        return [
            'status' => 'success',
            'message' => __( 'Overtime request approved.' ),
            'data' => compact( 'request' ),
        ];
    }

    public function reject( $requestId, $adminNote = null )
    {
        $request = OvertimeRequest::findOrFail( $requestId );

        if ( $request->status !== OvertimeRequest::STATUS_PENDING ) {
            throw new NotAllowedException( __( 'Only pending requests can be rejected.' ) );
        }

        $request->status = OvertimeRequest::STATUS_REJECTED;
        $request->approved_by = Auth::id();
        $request->approved_at = now();
        $request->admin_note = $adminNote;
        $request->save();

        return [
            'status' => 'success',
            'message' => __( 'Overtime request rejected.' ),
            'data' => compact( 'request' ),
        ];
    }

    public function getRequests( $filters = [] )
    {
        $query = OvertimeRequest::with( [ 'user', 'approver' ] );

        if ( ! empty( $filters['user_id'] ) ) {
            $query->where( 'user_id', $filters['user_id'] );
        }

        if ( ! empty( $filters['status'] ) ) {
            $query->where( 'status', $filters['status'] );
        }

        if ( ! empty( $filters['start_date'] ) ) {
            $query->whereDate( 'date', '>=', $filters['start_date'] );
        }

        if ( ! empty( $filters['end_date'] ) ) {
            $query->whereDate( 'date', '<=', $filters['end_date'] );
        }

        return $query->orderBy( 'id', 'desc' )->paginate( 50 );
    }

    public function getApprovedForPeriod( $userId, $start, $end )
    {
        return OvertimeRequest::where( 'user_id', $userId )
            ->where( 'status', OvertimeRequest::STATUS_APPROVED )
            ->whereBetween( 'date', [ $start, $end ] )
            ->get();
    }

    public function getMyRequests( $userId, $filters = [] )
    {
        $query = OvertimeRequest::with( 'approver' )->where( 'user_id', $userId );

        if ( ! empty( $filters['status'] ) ) {
            $query->where( 'status', $filters['status'] );
        }

        return $query->orderBy( 'id', 'desc' )->paginate( 50 );
    }
}
