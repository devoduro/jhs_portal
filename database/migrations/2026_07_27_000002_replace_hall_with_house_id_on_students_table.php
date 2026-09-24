<?php

use App\Models\House;
use App\Models\Student;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('house_id')->nullable()->after('hall')->constrained('houses')->nullOnDelete();
        });

        // Preserve any existing free-text hall values by turning each distinct name into a
        // House row and pointing the student at it, rather than silently discarding the data.
        Student::whereNotNull('hall')->where('hall', '!=', '')->select('id', 'hall')->get()
            ->groupBy('hall')
            ->each(function ($students, $hallName) {
                $house = House::firstOrCreate(['name' => $hallName]);
                Student::whereIn('id', $students->pluck('id'))->update(['house_id' => $house->id]);
            });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('hall');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('hall')->nullable()->after('reference_number');
        });

        Student::with('house')->whereNotNull('house_id')->get()->each(function (Student $student) {
            $student->update(['hall' => $student->house->name ?? null]);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('house_id');
        });
    }
};
