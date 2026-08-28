(() => {
    const catalogue = document.querySelector(
        '[data-catalog-tracking]'
    );

    if (!catalogue) {
        return;
    }

    const endpoint =
        catalogue.dataset.trackingEndpoint;

    const token =
        catalogue.dataset.trackingToken;

    if (!endpoint || !token) {
        return;
    }

    const cards = Array.from(
        catalogue.querySelectorAll(
            '.js-product-card'
        )
    );

    if (cards.length === 0) {
        return;
    }

    const query =
        catalogue.dataset.query || null;

    const category =
        catalogue.dataset.category || null;

    const sourcePath =
        window.location.pathname
        + window.location.search;

    let queue = [];
    let flushTimer = null;

    const buildEvent = (
        card,
        eventType
    ) => ({
        eventType,
        productId: Number(
            card.dataset.productId
        ),
        position: Number(
            card.dataset.position
        ),
    });

    const buildPayload = (events) =>
        JSON.stringify({
            _token: token,
            events,
            query,
            category,
            sourcePath,
        });

    const send = (
        events,
        preferBeacon = false
    ) => {
        if (events.length === 0) {
            return;
        }

        const body = buildPayload(events);

        if (
            preferBeacon
            && typeof navigator.sendBeacon
                === 'function'
        ) {
            const blob = new Blob(
                [body],
                {
                    type: 'application/json',
                }
            );

            if (
                navigator.sendBeacon(
                    endpoint,
                    blob
                )
            ) {
                return;
            }
        }

        fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type':
                    'application/json',
            },
            body,
            keepalive: true,
        }).catch(() => {
            /*
             * Le tracking ne doit jamais
             * bloquer l'expérience utilisateur.
             */
        });
    };

    const flush = (
        preferBeacon = false
    ) => {
        if (queue.length === 0) {
            return;
        }

        const events = queue;
        queue = [];

        if (flushTimer !== null) {
            window.clearTimeout(
                flushTimer
            );

            flushTimer = null;
        }

        send(events, preferBeacon);
    };

    const enqueue = (event) => {
        queue.push(event);

        if (queue.length >= 10) {
            flush();
            return;
        }

        if (flushTimer !== null) {
            return;
        }

        flushTimer = window.setTimeout(
            () => flush(),
            250
        );
    };

    /*
     * Une impression correspond à une carte
     * visible à au moins 50 % pendant 500 ms.
     */
    if (
        'IntersectionObserver' in window
    ) {
        const seen = new Set();
        const visible = new Set();
        const timers = new Map();

        const observer =
            new IntersectionObserver(
                (entries) => {
                    for (
                        const entry
                        of entries
                    ) {
                        const card =
                            entry.target;

                        const productId =
                            Number(
                                card.dataset
                                    .productId
                            );

                        const visibleEnough =
                            entry.isIntersecting
                            && entry
                                .intersectionRatio
                                >= 0.5;

                        if (visibleEnough) {
                            visible.add(
                                productId
                            );

                            if (
                                seen.has(productId)
                                || timers.has(
                                    productId
                                )
                            ) {
                                continue;
                            }

                            const timer =
                                window.setTimeout(
                                    () => {
                                        timers.delete(
                                            productId
                                        );

                                        if (
                                            !visible.has(
                                                productId
                                            )
                                        ) {
                                            return;
                                        }

                                        seen.add(
                                            productId
                                        );

                                        observer.unobserve(
                                            card
                                        );

                                        enqueue(
                                            buildEvent(
                                                card,
                                                'PRODUCT_CARD_IMPRESSION'
                                            )
                                        );
                                    },
                                    500
                                );

                            timers.set(
                                productId,
                                timer
                            );

                            continue;
                        }

                        visible.delete(
                            productId
                        );

                        const timer =
                            timers.get(
                                productId
                            );

                        if (
                            timer !== undefined
                        ) {
                            window.clearTimeout(
                                timer
                            );

                            timers.delete(
                                productId
                            );
                        }
                    }
                },
                {
                    threshold: [
                        0,
                        0.5,
                        1,
                    ],
                }
            );

        cards.forEach(
            (card) => observer.observe(card)
        );
    }

    /*
     * Le beacon permet d'envoyer le clic
     * même lorsque le navigateur quitte
     * immédiatement la page.
     */
    cards.forEach((card) => {
        const link =
            card.querySelector(
                '.js-product-card-link'
            );

        if (!link) {
            return;
        }

        link.addEventListener(
            'click',
            () => {
                send(
                    [
                        buildEvent(
                            card,
                            'PRODUCT_CARD_CLICK'
                        ),
                    ],
                    true
                );
            }
        );
    });

    document.addEventListener(
        'visibilitychange',
        () => {
            if (
                document.visibilityState
                === 'hidden'
            ) {
                flush(true);
            }
        }
    );

    window.addEventListener(
        'pagehide',
        () => flush(true)
    );
})();
