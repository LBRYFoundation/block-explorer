<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration{

    protected $connection = 'default';

    /**
     * Run the migrations.
     */
    public function up(): void{
        Schema::create('Blocks',static function(Blueprint $table): void{
            $table->rawColumn('Id','SERIAL');

            $table->string('Bits',20);
            $table->string('Chainwork',70);
            $table->unsignedInteger('Confirmations');
            $table->decimal('Difficulty',18,2);
            $table->string('Hash',70);
            $table->bigInteger('Height');
            $table->bigInteger('MedianTime');
            $table->string('MerkleRoot',70);
            $table->string('NameClaimRoot',70);
            $table->bigInteger('Nonce');
            $table->string('PreviousBlockHash',70);
            $table->string('NextBlockHash',70);
            $table->bigInteger('BlockSize');
            $table->string('Target',70);
            $table->bigInteger('BlockTime');
            $table->bigInteger('Version');
            $table->string('VersionHex',10);
            $table->text('TransactionHashes');
            $table->tinyInteger('TransactionsProcessed')->default(0);

            $table->dateTime('Created');
            $table->dateTime('Modified');

            $table->primary(['Id'],'PK_Block');
            $table->unique(['Hash'],'Idx_BlockHash');
            //TODO: CONSTRAINT `Cnt_TransactionHashesValidJson` CHECK(`TransactionHashes` IS NULL OR JSON_VALID(`TransactionHashes`))
            $table->index(['Height'],'Idx_BlockHeight');
            $table->index(['BlockTime'],'Idx_BlockTime');
            $table->index(['MedianTime'],'Idx_MedianTime');
            $table->index(['PreviousBlockHash'],'Idx_PreviousBlockHash');
            $table->index(['Created'],'Idx_BlockCreated');
            $table->index(['Modified'],'Idx_BlockModified');
        });

        Schema::create('Transactions',static function(Blueprint $table): void{
            $table->rawColumn('Id','SERIAL');

            $table->string('BlockHash',70);
            $table->unsignedInteger('InputCount');
            $table->unsignedInteger('OutputCount');
            $table->decimal('Value',18,8);
            $table->decimal('Fee',18,8)->default(0);
            $table->unsignedBigInteger('TransactionTime');
            $table->unsignedBigInteger('TransactionSize');
            $table->string('Hash',70);
            $table->integer('Version');
            $table->unsignedInteger('LockTime');
            $table->text('Raw');

            $table->dateTime('Created');
            $table->dateTime('Modified');
            $table->rawColumn('CreatedTime','INT UNSIGNED DEFAULT UNIX_TIMESTAMP() NOT NULL');
            //$table->unsignedInteger('CreatedTime')->default('UNIX_TIMESTAMP()');

            $table->primary(['Id'],'PK_Transaction');
            $table->foreign(['BlockHash'],'FK_TransactionBlockHash')->references(['Hash'])->on('Blocks');
            $table->unique(['Hash'],'Idx_TransactionHash');
            $table->index(['TransactionTime'],'Idx_TransactionTime');
            $table->index(['CreatedTime'],'Idx_TransactionCreatedTime');
            $table->index(['Created'],'Idx_TransactionCreated');
            $table->index(['Modified'],'Idx_TransactionModified');
        });

        Schema::create('Addresses',static function(Blueprint $table): void{
            $table->rawColumn('Id','SERIAL');

            $table->string('Address',40);
            $table->dateTime('FirstSeen');
            $table->decimal('TotalReceived',18,8)->default(0);
            $table->decimal('TotalSent',18,8)->default(0);
            $table->decimal('Balance',18,8)->virtualAs('TotalReceived - TotalSent')->persisted();
            $table->string('Tag',30);
            $table->string('TagUrl',200);
            $table->dateTime('Created');
            $table->dateTime('Modified');

            $table->primary(['Id'],'PK_Address');
            $table->unique(['Address'],'Idx_AddressAddress');
            $table->unique(['Tag'],'Idx_AddressTag');
            $table->index(['TotalReceived'],'Idx_AddressTotalReceived');
            $table->index(['TotalSent'],'Idx_AddressTotalSent');
            $table->index(['Balance'],'Idx_AddressBalance');
            $table->index(['Created'],'Idx_AddressCreated');
            $table->index(['Modified'],'Idx_AddressModified');
        });

        Schema::create('Inputs',static function(Blueprint $table): void{
            $table->rawColumn('Id','SERIAL');

            $table->unsignedBigInteger('TransactionId');
            $table->string('TransactionHash',70);
            $table->unsignedBigInteger('AddressId');
            $table->tinyInteger('IsCoinbase')->default(0);
            $table->string('Coinbase',70);
            $table->string('PrevoutHash',70);
            $table->unsignedInteger('PrevoutN');
            $table->tinyInteger('PrevoutSpendUpdated')->default(0);
            $table->unsignedInteger('Sequence');
            $table->decimal('Value',18,8);
            $table->text('ScriptSigAsm');
            $table->text('ScriptSigHex');
            $table->dateTime('Created');
            $table->dateTime('Modified');

            $table->primary(['Id'],'PK_Input');
            $table->foreign(['AddressId'],'FK_InputAddress')->references(['Id'])->on('Addresses');
            $table->foreign(['TransactionId'],'FK_InputTransaction')->references(['Id'])->on('Transactions');
            $table->index(['Value'],'Idx_InputValue');
            $table->index(['PrevoutHash'],'Idx_PrevoutHash');
            $table->index(['Created'],'Idx_InputCreated');
            $table->index(['Modified'],'Idx_InputModified');
        });

        Schema::create('InputsAddresses',static function(Blueprint $table): void{
            $table->unsignedBigInteger('InputId');
            $table->unsignedBigInteger('AddressId');

            $table->primary(['InputId','AddressId'],'PK_InputAddress');
            $table->foreign(['InputId'],'Idx_InputsAddressesInput')->references('Id')->on('Inputs');
            $table->foreign(['AddressId'],'Idx_InputsAddressesAddress')->references('Id')->on('Addresses');
        });

        Schema::create('Outputs',static function(Blueprint $table): void{
            $table->rawColumn('Id','SERIAL');

            $table->unsignedBigInteger('TransactionId');
            $table->decimal('Value',18,8);
            $table->unsignedInteger('Vout');
            $table->string('Type',20);
            $table->text('ScriptPubKeyAsm');
            $table->text('ScriptPubKeyHex');
            $table->unsignedInteger('RequiredSignatures');
            $table->string('Hash160',50);
            $table->text('Addresses');
            $table->tinyInteger('IsSpent')->default(0);
            $table->unsignedBigInteger('SpentByInputId');

            $table->dateTime('Created');
            $table->dateTime('Modified');

            $table->primary(['Id'],'PK_Output');
            $table->foreign(['TransactionId'],'FK_OutputTransaction')->references(['Id'])->on('Transactions');
            $table->foreign(['SpentByInputId'],'FK_OutputSpentByInput')->references(['Id'])->on('Inputs');
            //TODO CONSTRAINT `Cnt_AddressesValidJson` CHECK(`Addresses` IS NULL OR JSON_VALID(`Addresses`))
            $table->index(['Value'],'Idx_OutputValue');
            $table->index(['Created'],'Idx_OuptutCreated');
            $table->index(['Modified'],'Idx_OutputModified');
        });

        Schema::create('OutputsAddresses',static function(Blueprint $table): void{
            $table->unsignedBigInteger('OutputId');
            $table->unsignedBigInteger('AddressId');

            $table->primary(['OutputId','AddressId'],'PK_OutputAddress');
            $table->foreign(['OutputId'],'Idx_OutputsAddressesOutput')->references('Id')->on('Outputs');
            $table->foreign(['AddressId'],'Idx_OutputsAddressesAddress')->references('Id')->on('Addresses');
        });

        Schema::create('TransactionsAddresses',static function(Blueprint $table): void{
            $table->unsignedBigInteger('TransactionId');
            $table->unsignedBigInteger('AddressId');

            $table->decimal('DebitAmount',18,8)->default(0)->comment('Sum of the inputs to this address for the tx');
            $table->decimal('CreditAmount',18,8)->default(0)->comment('Sum of the outputs to this address for the tx');
            //$table->dateTime('TransactionTime')->default('UTC_TIMESTAMP()');
            $table->rawColumn('TransactionTime','DATETIME DEFAULT UTC_TIMESTAMP() NOT NULL');

            $table->primary(['TransactionId','AddressId'],'PK_TransactionAddress');
            $table->foreign(['TransactionId'],'Idx_TransactionsAddressesTransaction')->references('Id')->on('Transactions');
            $table->foreign(['AddressId'],'Idx_TransactionsAddressesAddress')->references('Id')->on('Addresses');
            $table->index(['TransactionTime'],'Idx_TransactionsAddressesTransactionTime');
            $table->index(['DebitAmount'],'Idx_TransactionsAddressesDebit');
            $table->index(['CreditAmount'],'Idx_TransactionsAddressesCredit');
        });

        Schema::create('Claims',static function(Blueprint $table): void{
            $table->rawColumn('Id','SERIAL');

            $table->string('TransactionHash',70);
            $table->unsignedInteger('Vout');
            $table->string('Name',1024);
            $table->char('ClaimId',40);
            $table->tinyInteger('ClaimType'); // 1 - CertificateType, 2 - StreamType
            $table->char('PublisherId',40)->comment('references a ClaimId with CertificateType');
            $table->string('PublisherSig',200);
            $table->text('Certificate');
            $table->unsignedInteger('TransactionTime');
            $table->string('Version',10);

            //Additional fields for easy indexing of stream types
            $table->string('Author',512);
            $table->mediumText('Description');
            $table->string('ContentType',162);
            $table->tinyInteger('IsNSFW')->default(0);
            $table->string('Language',20);
            $table->text('ThumbnailUrl');
            $table->text('Title');
            $table->decimal('Fee',18,8)->default(0);
            $table->char('FeeCurrency',3);
            $table->tinyInteger('IsFiltered')->default(0);

            $table->dateTime('Created');
            $table->dateTime('Modified');

            $table->primary(['Id'],'PK_Claim');
            $table->foreign(['TransactionHash'],'FK_ClaimTransaction')->references(['Hash'])->on('Transactions');
            //TODO $table->foreign(['PublisherId'],'FK_ClaimPublisher')->references(['ClaimId'])->on('Claims');
            $table->unique(['TransactionHash','Vout','ClaimId'],'Idx_ClaimUnique');
            //TODO CONSTRAINT `Cnt_ClaimCertificate` CHECK(`Certificate` IS NULL OR JSON_VALID(`Certificate`)) // certificate type
            $table->index(['ClaimId'],'Idx_Claim');
            $table->index(['TransactionTime'],'Idx_ClaimTransactionTime');
            $table->index(['Created'],'Idx_ClaimCreated');
            $table->index(['Modified'],'Idx_ClaimModified');

            //$table->index(['Author(191)'],'Idx_ClaimAuthor');
            $table->rawIndex('Author(191)','Idx_ClaimAuthor');
            $table->index(['ContentType'],'Idx_ClaimContentType');
            $table->index(['Language'],'Idx_ClaimLanguage');
            //$table->index(['Title(191)'],'Idx_ClaimTitle');
            $table->rawIndex('Title(191)','Idx_ClaimTitle');
        });

        Schema::create('ClaimStreams',static function(Blueprint $table): void{
            $table->unsignedBigInteger('Id');

            $table->mediumText('Stream');

            $table->primary(['Id'],'PK_ClaimStream');
            $table->foreign(['Id'],'PK_ClaimStreamClaim')->references('Id')->on('Claims');
        });

        Schema::create('PriceHistory',static function(Blueprint $table): void{
            $table->rawColumn('Id','SERIAL');

            $table->decimal('BTC',18,8)->default(0);
            $table->decimal('USD',18,2)->default(0);

            $table->dateTime('Created');

            $table->primary(['Id'],'PK_PriceHistory');
            $table->unique(['Created'],'Idx_PriceHistoryCreated');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void{
        Schema::dropIfExists('Blocks');

        Schema::dropIfExists('Transactions');

        Schema::dropIfExists('Addresses');

        Schema::dropIfExists('Inputs');

        Schema::dropIfExists('InputsAddresses');

        Schema::dropIfExists('Outputs');

        Schema::dropIfExists('OutputsAddresses');

        Schema::dropIfExists('TransactionsAddresses');

        Schema::dropIfExists('Claims');

        Schema::dropIfExists('ClaimStreams');

        Schema::dropIfExists('PriceHistory');
    }

};
