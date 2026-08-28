<?php
namespace App\Import\DTO;
use App\Import\Exception\ImportException;

final class ImportDtoFactory
{
    /** @param array<string,mixed> $row */
    public function create(string $type, array $row, int $record): object
    {
        return match ($type) {
            'users' => new UserImportDto(
                $record,
                $this->text($row, 'externalRef'),
                $this->text($row, 'email'),
                $this->nullableText($row, 'firstName'),
                $this->nullableText($row, 'lastName'),
                $this->date($row, 'createdAt')
            ),

            'categories' => new CategoryImportDto(
                $record,
                $this->text($row, 'externalRef'),
                $this->text($row, 'name'),
                $this->text($row, 'slug'),
                $this->nullableText($row, 'icon'),
                $this->boolean($row, 'isFeatured'),
                $this->boolean($row, 'showInNavigation'),
                $this->integer($row, 'navigationPosition')
            ),

            'products' => new ProductImportDto(
                $record,
                $this->text($row, 'externalRef'),
                $this->text($row, 'name'),
                $this->text($row, 'slug'),
                $this->text($row, 'description'),
                $this->integer($row, 'priceCents'),
                $this->integer($row, 'stock'),
                $this->text($row, 'categorySlug'),
                $this->nullableText($row, 'imageUrl'),
                $this->boolean($row, 'isActive')
            ),

            'orders' => new OrderImportDto(
                $record,
                $this->text($row, 'externalRef'),
                $this->text($row, 'userExternalRef'),
                $this->text($row, 'status'),
                $this->date($row, 'orderedAt'),
                $this->nullableInteger($row, 'totalCents')
            ),

            'order_items' => new OrderItemImportDto(
                $record,
                $this->text($row, 'orderExternalRef'),
                $this->nullableText($row, 'productExternalRef'),
                $this->text($row, 'productNameSnapshot'),
                $this->text($row, 'productSlugSnapshot'),
                $this->integer($row, 'quantity'),
                $this->integer($row, 'unitPriceCents')
            ),

            'reviews' => new ReviewImportDto(
                $record,
                $this->text($row, 'externalRef'),
                $this->text($row, 'userExternalRef'),
                $this->text($row, 'productExternalRef'),
                $this->integer($row, 'rating'),
                $this->nullableText($row, 'comment'),
                $this->date($row, 'createdAt')
            ),

            default => throw new ImportException(
                sprintf(
                    'Unsupported import type "%s".',
                    $type
                )
            ),
        };
    }
    /** @param array<string,mixed> $row */ private function text(array $row,string $key): string { $v=$row[$key]??null; return is_scalar($v)?trim((string)$v):''; }
    /** @param array<string,mixed> $row */ private function nullableText(array $row,string $key): ?string { $v=$this->text($row,$key); return ''===$v?null:$v; }
    /** @param array<string,mixed> $row */ private function integer(array $row,string $key): int { $v=$row[$key]??null; if (is_int($v)) return $v; if (is_float($v) && floor($v)===$v) return (int)$v; if (is_string($v) && preg_match('/^-?\d+$/',trim($v))) return (int)trim($v); throw new ImportException(sprintf('Record #%d: "%s" must be a strict integer.',$row['_record']??0,$key)); }
    /** @param array<string,mixed> $row */ private function nullableInteger(array $row,string $key): ?int { return !array_key_exists($key,$row)||null===$row[$key]||''===trim((string)$row[$key])?null:$this->integer($row,$key); }
    /** @param array<string,mixed> $row */ private function boolean(array $row,string $key): bool { $v=$row[$key]??null; if(is_bool($v))return $v; $n=strtolower(trim((string)$v)); return match($n){'1','true','yes'=>true,'0','false','no'=>false,default=>throw new ImportException(sprintf('Record #%d: "%s" must be a boolean.',$row['_record']??0,$key))}; }
    /** @param array<string,mixed> $row */ private function date(array $row,string $key): ?\DateTimeImmutable { $v=$row[$key]??null; if(null===$v)return null; if($v instanceof \DateTimeInterface)return \DateTimeImmutable::createFromInterface($v); if(!is_string($v)||''===trim($v))return null; try{$d=new \DateTimeImmutable(trim($v));}catch(\Throwable){throw new ImportException(sprintf('Record #%d: invalid date in "%s".',$row['_record']??0,$key));} if($d->format(\DateTimeInterface::ATOM)!==trim($v))throw new ImportException(sprintf('Record #%d: "%s" must use ISO-8601 ATOM format.',$row['_record']??0,$key)); return $d; }
}
