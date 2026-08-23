<?php
namespace App\Import\Importer;
use App\Entity\User;use App\Enum\DataOrigin;use App\Import\DTO\UserImportDto;use App\Repository\UserRepository;use Doctrine\ORM\EntityManagerInterface;use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
final readonly class UserImporter implements DataImporterInterface {
 public function __construct(private UserRepository $users,private EntityManagerInterface $em,private UserPasswordHasherInterface $hasher){}
 public function supports(string $type):bool{return 'users'===$type;}
 public function import(object $dto):ImportOutcome {assert($dto instanceof UserImportDto);$user=$this->users->findOneBy(['externalRef'=>$dto->externalRef]);if($user&&DataOrigin::Native===$user->getDataOrigin())throw new \DomainException('Native user is protected from import updates.');$emailOwner=$this->users->findOneBy(['email'=>strtolower($dto->email)]);if($emailOwner&&$emailOwner!==$user)throw new \DomainException('Email already belongs to another user.');if($user){$user->setEmail($dto->email)->setFirstName($dto->firstName)->setLastName($dto->lastName);$out=ImportOutcome::Updated;}else{$user=(new User($dto->createdAt))->setDataOrigin(DataOrigin::Imported)->setExternalRef($dto->externalRef)->setEmail($dto->email)->setFirstName($dto->firstName)->setLastName($dto->lastName);$user->setPassword($this->hasher->hashPassword($user,bin2hex(random_bytes(32))));$this->em->persist($user);$out=ImportOutcome::Created;}$this->em->flush();return $out;}
}
