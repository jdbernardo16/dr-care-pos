import { Queue } from "~/contracts/queue";
import { Popup } from "~/libraries/popup";
import orderTypePopup from "~/popups/ns-pos-order-type-popup.vue";

declare const POS;

export class TypeQueue implements Queue {
    constructor(private order) {}

    run() {
        return new Promise(async (resolve, reject) => {
            if (this.order.type === undefined) {
                /**
                 * Auto-select takeaway as the default order type
                 * for pharmacy POS (no popup needed).
                 */
                const types = POS.types.getValue();
                const takeawayType = Object.values(types).find(
                    (type: any) => type.identifier === "takeaway",
                ) as any;

                if (takeawayType) {
                    Object.values(types).forEach(
                        (type: any) => (type.selected = false),
                    );
                    takeawayType.selected = true;

                    await POS.triggerOrderTypeSelection(takeawayType);
                    POS.types.next(types);
                    resolve(true);
                } else {
                    /**
                     * Fallback: if takeaway is not available, show the popup
                     */
                    return Popup.show(orderTypePopup, { resolve, reject });
                }
            }
            resolve(true);
        });
    }
}
