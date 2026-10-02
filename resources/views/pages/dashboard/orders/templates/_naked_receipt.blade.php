@extends( 'layout.print' )
@section( 'layout.print.body' )
    @include( Hook::filter( 'ns-web-receipt-template', 'pages.dashboard.orders.templates._receipt' ) )
@endsection