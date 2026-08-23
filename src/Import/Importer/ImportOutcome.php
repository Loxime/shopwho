<?php
namespace App\Import\Importer;
enum ImportOutcome:string {case Created='created';case Updated='updated';case Skipped='skipped';}
