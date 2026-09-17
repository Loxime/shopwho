<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

final class TrackingIdentityService
{
    public const CONSENT_COOKIE =
        'shopwho_tracking_consent';

    public const VISITOR_SESSION_KEY =
        'tracking_visitor_id';

    public const SESSION_SESSION_KEY =
        'tracking_session_id';

    public function resolve(
        Request $request
    ): ?TrackingIdentity {
        if (!$request->hasSession()) {
            return null;
        }

        if (
            $request->cookies->get(
                self::CONSENT_COOKIE
            ) !== 'yes'
        ) {
            return null;
        }

        $session = $request->getSession();

        $visitorId = $this->stringValue(
            $session->get(
                self::VISITOR_SESSION_KEY
            )
        );

        if ($visitorId === null) {
            $visitorId =
                bin2hex(random_bytes(16));

            $session->set(
                self::VISITOR_SESSION_KEY,
                $visitorId
            );
        }

        $sessionId = $this->stringValue(
            $session->get(
                self::SESSION_SESSION_KEY
            )
        );

        if ($sessionId === null) {
            $sessionId =
                bin2hex(random_bytes(16));

            $session->set(
                self::SESSION_SESSION_KEY,
                $sessionId
            );
        }

        return new TrackingIdentity(
            $visitorId,
            $sessionId
        );
    }

    private function stringValue(
        mixed $value
    ): ?string {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }
}
