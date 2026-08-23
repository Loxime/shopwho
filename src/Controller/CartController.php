<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Service\OrderService;
use App\Service\TrackingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CartController extends AbstractController
{
    #[Route('/panier', name: 'app_cart', methods: ['GET'])]
    public function index(Request $request, ProductRepository $products, TrackingService $tracking): Response
    {
        [$lines, $totalCents] = $this->buildCart($request, $products);
        $tracking->track('CART_VIEW', null, ['line_count' => count($lines), 'total_cents' => $totalCents]);

        return $this->render('cart/index.html.twig', [
            'lines' => $lines,
            'total' => $totalCents / 100,
        ]);
    }

    #[Route('/panier/ajouter/{id}', name: 'app_cart_add', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function add(Product $product, Request $request, TrackingService $tracking): Response
    {
        if (!$product->isActive() || $product->getStock() < 1) {
            throw $this->createNotFoundException();
        }

        $session = $request->getSession();
        $cart = $session->get('cart', []);
        $cart[$product->getId()] = min(($cart[$product->getId()] ?? 0) + 1, $product->getStock());
        $session->set('cart', $cart);
        $tracking->track('ADD_TO_CART', $product->getId(), ['quantity' => $cart[$product->getId()], 'price_cents' => $product->getPriceCents()]);
        $this->addFlash('success', $product->getName().' ajouté au panier.');

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/panier/retirer/{id}', name: 'app_cart_remove', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function remove(int $id, Request $request, TrackingService $tracking): Response
    {
        $session = $request->getSession();
        $cart = $session->get('cart', []);
        unset($cart[$id]);
        $session->set('cart', $cart);
        $tracking->track('REMOVE_FROM_CART', $id);

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/panier/commander', name: 'app_cart_checkout', methods: ['POST'])]
    public function checkout(Request $request, ProductRepository $products, TrackingService $tracking, OrderService $orders): Response
    {
        [$lines, $totalCents] = $this->buildCart($request, $products);
        $tracking->track('CHECKOUT_STARTED', null, ['line_count' => count($lines), 'total_cents' => $totalCents]);
        if (!$lines) {
            return $this->redirectToRoute('app_cart');
        }

        $metadata = ['line_count' => count($lines), 'total_cents' => $totalCents];
        $user = $this->getUser();
        if ($user instanceof User) {
            $order = $orders->createFromCart($user, $lines, $totalCents);
            $metadata['order_id'] = $order->getId();
            $metadata['order_reference'] = $order->getReference();
        }

        $tracking->track('PURCHASE', null, $metadata);
        $request->getSession()->remove('cart');
        $this->addFlash('success', 'Commande simulée avec succès. Aucun paiement réel n’a été effectué.');

        return $this->redirectToRoute('app_cart');
    }

    private function buildCart(Request $request, ProductRepository $products): array
    {
        $cart = $request->getSession()->get('cart', []);
        $lines = [];
        $totalCents = 0;

        foreach ($cart as $id => $quantity) {
            $product = $products->find((int) $id);
            if (!$product || !$product->isActive()) {
                continue;
            }
            $quantity = max(1, min((int) $quantity, $product->getStock()));
            $lineTotal = $product->getPriceCents() * $quantity;
            $totalCents += $lineTotal;
            $lines[] = ['product' => $product, 'quantity' => $quantity, 'lineTotal' => $lineTotal / 100];
        }

        return [$lines, $totalCents];
    }
}
