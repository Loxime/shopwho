(() => {
    const offers = Array.from(
        document.querySelectorAll(
            '[data-special-offer]'
        )
    );

    if (offers.length === 0) {
        return;
    }

    const first = offers[0];

    const endpoint =
        first.dataset.trackingEndpoint;

    const token =
        first.dataset.trackingToken;

    if (!endpoint || !token) {
        return;
    }

    const sourcePath =
        window.location.pathname
        + window.location.search;

    let queue = [];
    let flushTimer = null;

    const buildEvent = (
        offer,
        eventType
    ) => ({
        eventType,
        offerId: Number(
            offer.dataset.offerId
        ),
        placement:
            offer.dataset.placement,
        position: Number(
            offer.dataset.position
        ),
    });

    const buildPayload = (
        events
    ) => JSON.stringify({
        _token: token,
        events,
        sourcePath,
    });

    const send = (
        events,
        preferBeacon = false
    ) => {
        if (events.length === 0) {
            return;
        }

        const body =
            buildPayload(events);

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
             * gêner l'utilisateur.
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

        send(
            events,
            preferBeacon
        );
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

        flushTimer =
            window.setTimeout(
                () => flush(),
                250
            );
    };

    if (
        'IntersectionObserver'
        in window
    ) {
        const seen = new Set();
        const visible = new Set();
        const timers = new Map();

        const keyFor = (
            offer
        ) => [
            offer.dataset.offerId,
            offer.dataset.placement,
            offer.dataset.position,
        ].join(':');

        const observer =
            new IntersectionObserver(
                (entries) => {
                    for (
                        const entry
                        of entries
                    ) {
                        const offer =
                            entry.target;

                        const key =
                            keyFor(offer);

                        const visibleEnough =
                            entry.isIntersecting
                            && entry
                                .intersectionRatio
                                >= 0.5;

                        if (visibleEnough) {
                            visible.add(key);

                            if (
                                seen.has(key)
                                || timers.has(key)
                            ) {
                                continue;
                            }

                            const timer =
                                window.setTimeout(
                                    () => {
                                        timers.delete(
                                            key
                                        );

                                        if (
                                            !visible.has(
                                                key
                                            )
                                        ) {
                                            return;
                                        }

                                        seen.add(key);

                                        observer.unobserve(
                                            offer
                                        );

                                        enqueue(
                                            buildEvent(
                                                offer,
                                                'SPECIAL_OFFER_IMPRESSION'
                                            )
                                        );
                                    },
                                    500
                                );

                            timers.set(
                                key,
                                timer
                            );

                            continue;
                        }

                        visible.delete(key);

                        const timer =
                            timers.get(key);

                        if (
                            timer !== undefined
                        ) {
                            window.clearTimeout(
                                timer
                            );

                            timers.delete(
                                key
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

        offers.forEach(
            (offer) =>
                observer.observe(offer)
        );
    }

    offers.forEach((offer) => {
        const link =
            offer.querySelector(
                '.js-special-offer-link'
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
                            offer,
                            'SPECIAL_OFFER_CLICK'
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
