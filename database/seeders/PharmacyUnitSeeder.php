<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PharmacyUnitSeeder extends Seeder
{
    /**
     * Create pharmacy-specific unit groups and units.
     *
     * @return void
     */
    public function run()
    {
        $authorId = Role::namespace('admin')->users->first()->id ?? 1;

        // Unit Group 1: Pieces (tablets, capsules, sachets)
        $piecesGroup = UnitGroup::create([
            'name' => 'Pharmacy Pieces',
            'description' => 'Individual countable units like tablets, capsules, and sachets.',
            'author_id' => $authorId,
            'uuid' => Str::uuid(),
        ]);

        $piece = Unit::create([
            'name' => 'Piece',
            'value' => 1,
            'identifier' => 'pharm-piece-' . Str::random(5),
            'base_unit' => true,
            'group_id' => $piecesGroup->id,
            'author_id' => $authorId,
            'uuid' => Str::uuid(),
        ]);

        $box = Unit::create([
            'name' => 'Box',
            'value' => 10,
            'identifier' => 'pharm-box-' . Str::random(5),
            'base_unit' => false,
            'group_id' => $piecesGroup->id,
            'author_id' => $authorId,
            'uuid' => Str::uuid(),
        ]);

        $strip = Unit::create([
            'name' => 'Strip',
            'value' => 5,
            'identifier' => 'pharm-strip-' . Str::random(5),
            'base_unit' => false,
            'group_id' => $piecesGroup->id,
            'author_id' => $authorId,
            'uuid' => Str::uuid(),
        ]);

        // Unit Group 2: Liquid (syrups, suspensions, drops)
        $liquidGroup = UnitGroup::create([
            'name' => 'Pharmacy Liquid',
            'description' => 'Liquid medicine measurements like bottles and milliliters.',
            'author_id' => $authorId,
            'uuid' => Str::uuid(),
        ]);

        $bottle = Unit::create([
            'name' => 'Bottle',
            'value' => 1,
            'identifier' => 'pharm-bottle-' . Str::random(5),
            'base_unit' => true,
            'group_id' => $liquidGroup->id,
            'author_id' => $authorId,
            'uuid' => Str::uuid(),
        ]);

        $pack = Unit::create([
            'name' => 'Pack (6 Bottles)',
            'value' => 6,
            'identifier' => 'pharm-pack-' . Str::random(5),
            'base_unit' => false,
            'group_id' => $liquidGroup->id,
            'author_id' => $authorId,
            'uuid' => Str::uuid(),
        ]);

        // Unit Group 3: Topical (creams, ointments, gels)
        $topicalGroup = UnitGroup::create([
            'name' => 'Pharmacy Topical',
            'description' => 'Topical application units like tubes and jars.',
            'author_id' => $authorId,
            'uuid' => Str::uuid(),
        ]);

        $tube = Unit::create([
            'name' => 'Tube',
            'value' => 1,
            'identifier' => 'pharm-tube-' . Str::random(5),
            'base_unit' => true,
            'group_id' => $topicalGroup->id,
            'author_id' => $authorId,
            'uuid' => Str::uuid(),
        ]);

        $jar = Unit::create([
            'name' => 'Jar',
            'value' => 1,
            'identifier' => 'pharm-jar-' . Str::random(5),
            'base_unit' => false,
            'group_id' => $topicalGroup->id,
            'author_id' => $authorId,
            'uuid' => Str::uuid(),
        ]);

        return [
            'pieces_group' => $piecesGroup,
            'piece' => $piece,
            'box' => $box,
            'strip' => $strip,
            'liquid_group' => $liquidGroup,
            'bottle' => $bottle,
            'pack' => $pack,
            'topical_group' => $topicalGroup,
            'tube' => $tube,
            'jar' => $jar,
        ];
    }
}
