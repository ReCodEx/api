<?php

namespace App\Model\Entity;

use Doctrine\Common\Collections\Collection;


interface IAssignment {

  function getGroup(): Group;
  function isPublic(): bool;
  function isBonus(): bool;
  function getVersion(): int;
  function getLocalizedTexts(): Collection;
  function getLocalizedTextByLocale(string $locale): ?LocalizedEntity;

}
