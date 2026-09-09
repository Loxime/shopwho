<?php

namespace App\Enum;

enum TrackingEventType: string
{
    case PageView = 'PAGE_VIEW';
    case Search = 'SEARCH';
    case CategoryView = 'CATEGORY_VIEW';

    case ProductView = 'PRODUCT_VIEW';
    case ProductCardImpression =
        'PRODUCT_CARD_IMPRESSION';
    case ProductCardClick =
        'PRODUCT_CARD_CLICK';

    case CartView = 'CART_VIEW';
    case AddToCart = 'ADD_TO_CART';
    case CartQuantityChanged =
        'CART_QUANTITY_CHANGED';
    case RemoveFromCart =
        'REMOVE_FROM_CART';

    case CheckoutStarted =
        'CHECKOUT_STARTED';
    case Purchase = 'PURCHASE';

    case FavoriteAdded =
        'FAVORITE_ADDED';
    case FavoriteRemoved =
        'FAVORITE_REMOVED';

    case RecommendationImpression =
        'RECOMMENDATION_IMPRESSION';
    case RecommendationClick =
        'RECOMMENDATION_CLICK';

    case SpecialOfferImpression =
        'SPECIAL_OFFER_IMPRESSION';
    case SpecialOfferClick =
        'SPECIAL_OFFER_CLICK';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $event): string =>
                $event->value,
            self::cases()
        );
    }
}
