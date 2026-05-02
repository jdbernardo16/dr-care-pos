@extends( 'layout.dashboard' )

@section( 'layout.dashboard.body' )
<div>
    @include( Hook::filter( 'ns-dashboard-header-file', '../common/dashboard-header' ) )
    <div id="dashboard-content" class="px-4">
        @include( 'common.dashboard.title' )
        <ns-payroll-detail run-id="{{ $run->id }}" user-name="{{ $run->user->username }}"></ns-payroll-detail>
    </div>
</div>
@endsection
