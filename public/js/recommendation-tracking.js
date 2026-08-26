(() => {
    const section = document.querySelector(
        '[data-recommendation-tracking]'
    );

    if (!section) {
        return;
    }

    const endpoint =
        section.dataset.trackingEndpoint;

    const token =
        section.dataset.trackingToken;

    if (!endpoint || !token) {
        return;
    }

    const cards = Array.from(
        section.querySelectorAll(
            '.js-recommendation-card'
        )
    );

    if (cards.length === 0) {
        return;
    }

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
        strategy:
            card.dataset.strategy,
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
             * Une panne de tracking ne doit
             * jamais perturber la navigation.
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

    const enqueue = (
        event
    ) => {
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
            card
        ) => [
            card.dataset.productId,
            card.dataset.strategy,
            card.dataset.position,
        ].join(':');

        const observer =
            new IntersectionObserver(
                (entries) => {
                    for (
                        const entry
                        of entries
                    ) {
                        const card =
                            entry.target;

                        const key =
                            keyFor(card);

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
                                            card
                                        );

                                        enqueue(
                                            buildEvent(
                                                card,
                                                'RECOMMENDATION_IMPRESSION'
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

        cards.forEach(
            (card) =>
                observer.observe(card)
        );
    }

    cards.forEach(
        (card) => {
            const link =
                card.querySelector(
                    '.js-recommendation-link'
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
                                'RECOMMENDATION_CLICK'
                            ),
                        ],
                        true
                    );
                }
            );
        }
    );

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
