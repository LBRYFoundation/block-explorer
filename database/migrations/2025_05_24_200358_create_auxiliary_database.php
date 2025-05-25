<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration{

    protected $connection = 'localdb';

    /**
     * Run the migrations.
     */
    public function up(): void{
        Schema::create('TagAddressRequests',static function(Blueprint $table): void{
            $table->integer('Id');

            $table->string('Address',35);
            $table->decimal('VerificationAmount',18,8);
            $table->string('Tag',30);
            $table->string('TagUrl',200);
            $table->tinyInteger('IsVerified')->default(0);

            $table->dateTime('Created');
            $table->dateTime('Modified');

            $table->primary(['Id'],'PK_TagAddressRequest');
            $table->unique(['Address','VerificationAmount'],'Idx_TagAddressRequestId');
            $table->index(['VerificationAmount'],'Idx_TagAddressRequestVerificationAmount');
            $table->index(['Address'],'Idx_TagAddressRequestAddress');
            $table->index(['Created'],'Idx_TagAddressRequestCreated');
            $table->index(['Modified'],'Idx_TagAddressRequestModified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void{
        Schema::dropIfExists('TagAddressRequests');
    }

};
