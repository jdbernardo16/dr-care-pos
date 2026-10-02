<?php

namespace App\Listeners;

use App\Events\OrderAfterCreatedEvent;
use App\Jobs\ComputeDayReportJob;
use App\Jobs\IncreaseCashierStatsJob;
use App\Jobs\ProcessCustomerOwedAndRewardsJob;
use App\Jobs\RecordOrderChangeJob;
use App\Jobs\ResolveInstalmentJob;
use App\Jobs\SaveOrderSettingJob;
use Illuminate\Support\Facades\Bus;

class OrderAfterCreatedEventListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object $event
     * @return void
     */
    public function handle( OrderAfterCreatedEvent $event )
    {
        /**
         * The "created" event is fired on both insert and update
         * operations (see NsModel::saveWithRelationships). We'll
         * only proceed for orders that have just been created to
         * prevent the cashier stats from being credited twice
         * when an order is updated (e.g. a hold order being charged).
         */
        if ( ! $event->order->wasRecentlyCreated ) {
            return;
        }

        // The order settings should be immediately
        // be created whent he order is created
        SaveOrderSettingJob::dispatchSync( $event->order );

        Bus::chain( [
            new IncreaseCashierStatsJob( $event->order ),
            new ProcessCustomerOwedAndRewardsJob( $event->order ),
            new ResolveInstalmentJob( $event->order ),
            new ComputeDayReportJob,
            new RecordOrderChangeJob( $event->order ),
        ] )->dispatch();
    }
}
