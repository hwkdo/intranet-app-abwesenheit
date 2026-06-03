<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAbwesenheit\Concerns;

use Hwkdo\IntranetAppAbwesenheit\Support\AbwesenheitModels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait HasAbwesenheitOrganisation
{
    public function getIstVorgesetzterAttribute(): bool
    {
        $gvp = $this->gvp;
        if (! $gvp) {
            return false;
        }

        return (int) $this->id === (int) $gvp->vorgesetzter_id
            || (int) $this->id === (int) $gvp->stellvertreter_id;
    }

    /**
     * @return Collection<int, Model>|false
     */
    public function getUntergebene(bool $rekursiv = true): Collection|false
    {
        if (! $this->ist_vorgesetzter) {
            return false;
        }

        $gvp = $this->gvp()->with(['mitarbeiter', 'mitarbeiter_secondary', 'child.mitarbeiter', 'child.mitarbeiter_secondary', 'child.child.mitarbeiter', 'child.child.mitarbeiter_secondary', 'child.child.child.mitarbeiter', 'child.child.child.mitarbeiter_secondary'])->first();
        if (! $gvp) {
            return collect();
        }

        $untergebene = collect();

        foreach ($gvp->mitarbeiter()->where('id', '!=', $this->id)->get() as $ma) {
            $untergebene->push($ma);
        }
        foreach ($gvp->mitarbeiter_secondary as $ma) {
            $untergebene->push($ma);
        }

        if ($gvp->child->isNotEmpty()) {
            foreach ($gvp->child as $childGvp) {
                if ($rekursiv) {
                    foreach ($childGvp->mitarbeiter()->where('id', '!=', $this->id)->get() as $ma) {
                        $untergebene->push($ma);
                    }
                    foreach ($childGvp->mitarbeiter_secondary as $ma) {
                        $untergebene->push($ma);
                    }
                } elseif ($childGvp->vorgesetzter) {
                    $untergebene->push($childGvp->vorgesetzter);
                }

                if ($rekursiv && $childGvp->child->isNotEmpty()) {
                    foreach ($childGvp->child as $childGvp2) {
                        foreach ($childGvp2->mitarbeiter()->where('id', '!=', $this->id)->get() as $ma) {
                            $untergebene->push($ma);
                        }
                        foreach ($childGvp2->mitarbeiter_secondary as $ma) {
                            $untergebene->push($ma);
                        }
                        if ($childGvp2->child->isNotEmpty()) {
                            foreach ($childGvp2->child as $childGvp3) {
                                foreach ($childGvp3->mitarbeiter()->where('id', '!=', $this->id)->get() as $ma) {
                                    $untergebene->push($ma);
                                }
                                foreach ($childGvp3->mitarbeiter_secondary as $ma) {
                                    $untergebene->push($ma);
                                }
                            }
                        }
                    }
                }
            }
        }

        return $untergebene
            ->filter(fn (Model $user): bool => (int) $user->id !== (int) $this->id)
            ->unique('id')
            ->values();
    }

    /**
     * @return Collection<int, Model>
     */
    public function getGvpKollegen(): Collection
    {
        $gvpModel = AbwesenheitModels::gvp();
        $kollegen = collect();

        foreach ($this->getVorgesetzte() as $vorgesetzter) {
            $kollegen->push($vorgesetzter);
        }

        $gvp = $this->gvp()->with(['mitarbeiter', 'parent.mitarbeiter', 'parent.child.mitarbeiter', 'child.mitarbeiter'])->first();
        if (! $gvp) {
            return $kollegen
                ->filter(fn (?Model $kollege): bool => $kollege && (int) $kollege->id !== (int) $this->id)
                ->unique('id')
                ->values();
        }

        foreach ($gvp->mitarbeiter as $kollege) {
            $kollegen->push($kollege);
        }

        if ($gvp->kuerzel === 'G' && $gvp->parent) {
            foreach ($gvp->parent->mitarbeiter as $kollege) {
                $kollegen->push($kollege);
            }
            foreach ($gvp->parent->child as $gruppe) {
                foreach ($gruppe->mitarbeiter as $kollege) {
                    $kollegen->push($kollege);
                }
            }
        }

        if ($gvp->kuerzel === 'A') {
            foreach ($gvp->child as $gruppe) {
                foreach ($gruppe->mitarbeiter as $kollege) {
                    $kollegen->push($kollege);
                }
            }
        }

        if ($this->ist_al) {
            $meinGb = match ($gvp->kuerzel) {
                'GB' => $gvp,
                'A', 'Stab' => $gvp->parent,
                'G', 'FB' => $gvp->parent?->parent,
                default => null,
            };

            if ($meinGb) {
                $abteilungenImGb = $gvpModel::with('vorgesetzter')
                    ->where('kuerzel', 'A')
                    ->where('parent_id', $meinGb->id)
                    ->get();
                foreach ($abteilungenImGb as $abteilung) {
                    if ($abteilung->vorgesetzter) {
                        $kollegen->push($abteilung->vorgesetzter);
                    }
                }
            }
        }

        if ($this->ist_vorgesetzter) {
            $gefuehrteGvps = $gvpModel::with(['mitarbeiter', 'mitarbeiter_secondary', 'stellvertreter', 'vorgesetzter'])
                ->where('vorgesetzter_id', $this->id)
                ->get();

            foreach ($gefuehrteGvps as $gefuehrteGvp) {
                foreach ($gefuehrteGvp->mitarbeiter as $mitarbeiter) {
                    $kollegen->push($mitarbeiter);
                }
                foreach ($gefuehrteGvp->mitarbeiter_secondary as $mitarbeiterSecondary) {
                    $kollegen->push($mitarbeiterSecondary);
                }
                if ($gefuehrteGvp->vorgesetzter) {
                    $kollegen->push($gefuehrteGvp->vorgesetzter);
                }
                if ($gefuehrteGvp->stellvertreter) {
                    $kollegen->push($gefuehrteGvp->stellvertreter);
                }
            }
        }

        return $kollegen
            ->filter(fn (?Model $kollege): bool => $kollege && (int) $kollege->id !== (int) $this->id)
            ->unique('id')
            ->values();
    }
}
