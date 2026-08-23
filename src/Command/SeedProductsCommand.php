<?php

namespace App\Command;

use App\Entity\Category;
use App\Entity\Product;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:seed-products', description: 'Ajoute un petit catalogue de démonstration si la base est vide.')]
class SeedProductsCommand extends Command
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly CategoryRepository $categories,
        private readonly EntityManagerInterface $em,
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->products->count([]) > 0) {
            $output->writeln('<comment>Catalogue déjà rempli : aucun produit ajouté.</comment>');
            return Command::SUCCESS;
        }

        $items = [
            ['Casque Nova X1', 'casque-nova-x1', 'Casque sans fil confortable pour le quotidien.', 8990, 32, 'Audio'],
            ['Clavier Pulse 75', 'clavier-pulse-75', 'Clavier compact mécanique 75 % pour bureau et jeu.', 11990, 18, 'Informatique'],
            ['Souris Orbit Pro', 'souris-orbit-pro', 'Souris légère et précise avec connexion sans fil.', 6990, 41, 'Informatique'],
            ['Sac Nomad 24L', 'sac-nomad-24l', 'Sac urbain polyvalent avec compartiment ordinateur.', 7490, 25, 'Lifestyle'],
            ['Montre Move S', 'montre-move-s', 'Montre connectée dédiée au suivi quotidien.', 13990, 16, 'Sport'],
            ['Lampe Halo', 'lampe-halo', 'Lampe de bureau réglable avec éclairage doux.', 4990, 50, 'Maison'],
            ['Écouteurs Drift', 'ecouteurs-drift', 'Écouteurs compacts avec boîtier de recharge.', 5990, 38, 'Audio'],
            ['Support Arc', 'support-arc', 'Support aluminium pour ordinateur portable.', 3490, 60, 'Accessoires'],
        ];

        $categoryEntities = [];
        foreach (array_values(array_unique(array_column($items, 5))) as $position => $categoryName) {
            $categorySlug = strtolower($categoryName);
            $category = $this->categories->findOneBy(['slug' => $categorySlug]);
            if (!$category) {
                $category = (new Category())
                    ->setName($categoryName)
                    ->setSlug($categorySlug)
                    ->setNavigationPosition(($position + 1) * 10)
                    ->setIsFeatured($position < 2);
                $this->em->persist($category);
            }
            $categoryEntities[$categoryName] = $category;
        }

        foreach ($items as [$name, $slug, $description, $price, $stock, $categoryName]) {
            $product = (new Product())
                ->setName($name)
                ->setSlug($slug)
                ->setDescription($description)
                ->setPriceCents($price)
                ->setStock($stock)
                ->setCategory($categoryEntities[$categoryName])
                ->setIsActive(true);
            $this->em->persist($product);
        }

        $this->em->flush();
        $output->writeln('<info>8 produits de démonstration ajoutés.</info>');

        return Command::SUCCESS;
    }
}
