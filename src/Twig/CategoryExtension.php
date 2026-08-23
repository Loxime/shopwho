<?php

namespace App\Twig;

use App\Repository\CategoryRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CategoryExtension extends AbstractExtension
{
    public function __construct(private readonly CategoryRepository $categories) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('shopwho_navigation_categories', $this->categories->findForNavigation(...)),
            new TwigFunction('shopwho_all_categories', fn (): array => $this->categories->findBy([], ['name' => 'ASC'])),
            new TwigFunction('shopwho_featured_categories', $this->categories->findFeatured(...)),
        ];
    }
}
