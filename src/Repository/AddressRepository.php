<?php

namespace App\Repository;

use App\Entity\Address;
use App\Entity\User;
use App\Enum\AddressType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AddressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Address::class);
    }

    public function findForUserAndType(
        User $user,
        AddressType $type,
    ): ?Address {
        return $this->findOneBy([
            'user' => $user,
            'type' => $type,
        ]);
    }
}
