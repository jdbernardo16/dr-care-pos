@extends( 'layout.base' )
@section( 'layout.base.body' )
    @include( Hook::filter( 'ns-web-receipt-template', 'pages.dashboard.orders.templates._payment_receipt' ) )
@endsection