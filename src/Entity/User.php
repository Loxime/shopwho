<?php

namespace App\Entity;

use App\Enum\DataOrigin;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email = '';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(length: 180, unique: true, nullable: true)]
    private ?string $externalRef = null;

    #[ORM\Column(length: 20, enumType: DataOrigin::class)]
    private DataOrigin $dataOrigin = DataOrigin::Native;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(length: 255)]
    private string $password = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Order> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Order::class)]
    #[ORM\OrderBy(['orderedAt' => 'DESC', 'id' => 'DESC'])]
    private Collection $orders;

    /** @var Collection<int, Review> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Review::class)]
    private Collection $reviews;

    /** @var Collection<int, Favorite> */
    #[ORM\OneToMany(
        mappedBy: 'user',
        targetEntity: Favorite::class,
        orphanRemoval: true
    )]
    private Collection $favorites;

    /** @var Collection<int, Notification> */
    #[ORM\OneToMany(
        mappedBy: 'user',
        targetEntity: Notification::class,
        orphanRemoval: true
    )]
    private Collection $notifications;

    public function __construct(?\DateTimeImmutable $createdAt = null)
    {
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->orders = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->favorites = new ArrayCollection();
        $this->notifications = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = strtolower(trim($email));

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $this->normalizeNullableValue($firstName);

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): self
    {
        $this->lastName = $this->normalizeNullableValue($lastName);

        return $this;
    }

    public function getExternalRef(): ?string
    {
        return $this->externalRef;
    }

    public function setExternalRef(?string $externalRef): self
    {
        $this->externalRef = $this->normalizeNullableValue($externalRef);

        return $this;
    }

    public function getDataOrigin(): DataOrigin
    {
        return $this->dataOrigin;
    }

    public function setDataOrigin(DataOrigin $dataOrigin): self
    {
        $this->dataOrigin = $dataOrigin;

        return $this;
    }

    public function getDisplayName(): string
    {
        $name = trim(sprintf('%s %s', $this->firstName ?? '', $this->lastName ?? ''));

        return $name !== '' ? $name : $this->email;
    }

    public function getPublicDisplayName(): string
    {
        if ($this->firstName) {
            return $this->lastName
                ? sprintf('%s %s.', $this->firstName, mb_strtoupper(mb_substr($this->lastName, 0, 1)))
                : $this->firstName;
        }

        return 'Client Shopwho';
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Order> */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    /** @return Collection<int, Review> */
    public function getReviews(): Collection { return $this->reviews; }

    /**
     * @return Collection<int, Favorite>
     */
    public function getFavorites(): Collection
    {
        return $this->favorites;
    }
    
    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }
        
    public function eraseCredentials(): void
    {
    }

    private function normalizeNullableValue(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
