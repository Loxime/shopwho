<?php
namespace App\Import\Importer;
use App\Entity\Product;use App\Import\DTO\ProductImportDto;use App\Repository\CategoryRepository;use App\Repository\ProductRepository;use Doctrine\ORM\EntityManagerInterface;
final readonly class ProductImporter implements DataImporterInterface {
 public function __construct(private ProductRepository $products,private CategoryRepository $categories,private EntityManagerInterface $em){}
 public function supports(string $type):bool{return 'products'===$type;}
 public function import(object $dto):ImportOutcome {assert($dto instanceof ProductImportDto);$product=$this->products->findOneBy(['externalRef'=>$dto->externalRef]);$slugOwner=$this->products->findOneBy(['slug'=>$dto->slug]);if($slugOwner&&$slugOwner!==$product)throw new \DomainException('Slug already used by another product.');$category=$this->categories->findOneBy(['slug'=>$dto->categorySlug]);if(!$category)throw new \DomainException(sprintf('Unknown category "%s".',$dto->categorySlug));$out=$product?ImportOutcome::Updated:ImportOutcome::Created;$product??=(new Product())->setExternalRef($dto->externalRef);$product->setName($dto->name)->setSlug($dto->slug)->setDescription($dto->description)->setPriceCents($dto->priceCents)->setStock($dto->stock)->setCategory($category)->setImageUrl($dto->imageUrl)->setIsActive($dto->isActive);$this->em->persist($product);$this->em->flush();return $out;}
}
