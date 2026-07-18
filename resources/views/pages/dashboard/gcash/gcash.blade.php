@extends( 'layout.dashboard' )
@section( 'layout.dashboard.body' )
<div class="flex-auto flex flex-col">
    @include( Hook::filter( 'ns-dashboard-header-file', '../common/dashboard-header' ) )
    <div id="dashboard-content" class="px-4 flex-auto flex flex-col overflow-hidden">
        <ns-gcash></ns-gcash>
    </div>
</div>
@endsection
