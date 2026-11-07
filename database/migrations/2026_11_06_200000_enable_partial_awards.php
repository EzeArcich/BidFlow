<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private function base(Blueprint $t): void
    {
        $t->ulid('id')->primary();
        $t->foreignUlid('organization_id')->constrained()->restrictOnDelete();
        $t->unique(['organization_id', 'id']);
        $t->timestampsTz();
    }

    private function fk(Blueprint $t, array $cols, string $target, array $refs, string $name): void
    {
        $t->index($cols, $name.'_idx');
        $t->foreign($cols, $name)->references($refs)->on($target)->restrictOnDelete();
    }

    public function up(): void
    {
        DB::statement('ALTER TABLE tenders DROP CONSTRAINT tenders_check_1');
        DB::statement("ALTER TABLE tenders ADD CONSTRAINT tenders_check_1 CHECK (status IN ('draft','published','closed','partially_awarded','awarded','completed','cancelled','expired'))");
        Schema::table('bids', function (Blueprint $t): void {
            $t->jsonb('submitted_snapshot')->nullable();
            $t->unique(['organization_id', 'tender_id', 'supplier_id', 'id'], 'bids_supplier_identity');
        });
        Schema::table('bid_items', function (Blueprint $t): void {
            $t->integer('delivery_days')->nullable();
            $t->text('conditions')->nullable();
            $t->unique(['organization_id', 'tender_id', 'bid_id', 'id'], 'bid_items_offer_identity');
        });
        DB::statement('ALTER TABLE bid_items ADD CONSTRAINT bid_item_delivery CHECK (delivery_days >= 0)');
        Schema::table('awards', function (Blueprint $t): void {
            $t->ulid('bid_id')->nullable()->change();
            $t->boolean('legacy')->default(true);
            $t->string('request_key', 100)->nullable();
            $t->string('request_hash', 64)->nullable();
            $t->string('status')->default('confirmed');
            $t->char('currency', 3)->default('ARS');
            $t->decimal('total_amount', 15, 2)->default(0);
            $t->unique(['organization_id', 'tender_id', 'request_key'], 'award_request_key');
            $t->unique(['organization_id', 'tender_id', 'id'], 'award_tender_identity');
            $this->fk($t, ['organization_id', 'tender_id'], 'tenders', ['organization_id', 'id'], 'award_tender_fk');
        });
        DB::statement('ALTER TABLE awards DROP CONSTRAINT awards_tender_id_unique');
        DB::statement('ALTER TABLE awards DROP CONSTRAINT awards_bid_id_unique');
        DB::statement('CREATE UNIQUE INDEX legacy_award_tender ON awards(tender_id) WHERE legacy');
        DB::statement('CREATE UNIQUE INDEX legacy_award_bid ON awards(bid_id) WHERE legacy');
        DB::statement('ALTER TABLE awards ALTER COLUMN legacy SET DEFAULT false');
        DB::statement('ALTER TABLE awards ADD CONSTRAINT award_mode CHECK ((legacy AND bid_id IS NOT NULL) OR (NOT legacy AND bid_id IS NULL AND request_key IS NOT NULL AND length(request_key)>0 AND request_hash IS NOT NULL))');
        DB::statement("ALTER TABLE awards ADD CONSTRAINT award_economics CHECK (status='confirmed' AND currency='ARS' AND total_amount>=0)");
        Schema::create('award_lines', function (Blueprint $t): void {
            $this->base($t);
            foreach (['tender_id', 'award_id', 'bid_id', 'bid_item_id', 'tender_item_id', 'supplier_id'] as $col) {
                $t->ulid($col);
            }
            $t->char('currency', 3)->default('ARS');
            $t->decimal('quantity', 12, 3);
            $t->decimal('unit_price', 15, 2);
            $t->decimal('subtotal', 15, 2);
            $t->text('notes')->nullable();
            $t->jsonb('snapshot');
            $this->fk($t, ['organization_id', 'tender_id', 'award_id'], 'awards', ['organization_id', 'tender_id', 'id'], 'award_line_header');
            $this->fk($t, ['organization_id', 'tender_id', 'supplier_id', 'bid_id'], 'bids', ['organization_id', 'tender_id', 'supplier_id', 'id'], 'award_line_supplier');
            $this->fk($t, ['organization_id', 'tender_id', 'bid_id', 'bid_item_id'], 'bid_items', ['organization_id', 'tender_id', 'bid_id', 'id'], 'award_line_bid_item');
            $this->fk($t, ['organization_id', 'tender_id', 'tender_item_id'], 'tender_items', ['organization_id', 'tender_id', 'id'], 'award_line_tender_item');
            $t->unique(['award_id', 'bid_item_id']);
            $t->unique(['organization_id', 'award_id', 'supplier_id', 'id'], 'award_line_order_identity');
        });
        DB::statement("ALTER TABLE award_lines ADD CONSTRAINT award_line_money CHECK (quantity>0 AND unit_price>=0 AND subtotal=round(quantity*unit_price,2) AND currency='ARS')");
        Schema::table('work_orders', function (Blueprint $t): void {
            $t->ulid('supplier_id')->nullable();
            $t->ulid('tender_id')->nullable();
            $this->fk($t, ['organization_id', 'tender_id', 'award_id'], 'awards', ['organization_id', 'tender_id', 'id'], 'order_award_identity');
            $this->fk($t, ['organization_id', 'supplier_id'], 'organization_suppliers', ['organization_id', 'supplier_id'], 'order_supplier_link');
            $t->unique(['award_id', 'supplier_id'], 'order_award_supplier');
            $t->unique(['organization_id', 'award_id', 'supplier_id', 'id'], 'order_line_identity');
        });
        DB::statement('ALTER TABLE work_orders DROP CONSTRAINT work_orders_award_id_unique');
        DB::statement('CREATE UNIQUE INDEX legacy_order_award ON work_orders(award_id) WHERE supplier_id IS NULL');
        DB::statement('ALTER TABLE work_orders ADD CONSTRAINT order_identity_pair CHECK ((supplier_id IS NULL)=(tender_id IS NULL))');
        Schema::create('work_order_lines', function (Blueprint $t): void {
            $this->base($t);
            foreach (['work_order_id', 'award_id', 'supplier_id', 'award_line_id'] as $col) {
                $t->ulid($col);
            }
            $t->decimal('quantity', 12, 3);
            $t->decimal('unit_price', 15, 2);
            $t->decimal('subtotal', 15, 2);
            $t->char('currency', 3)->default('ARS');
            $t->jsonb('snapshot');
            $t->unique('award_line_id');
            $this->fk($t, ['organization_id', 'award_id', 'supplier_id', 'work_order_id'], 'work_orders', ['organization_id', 'award_id', 'supplier_id', 'id'], 'order_line_parent');
            $this->fk($t, ['organization_id', 'award_id', 'supplier_id', 'award_line_id'], 'award_lines', ['organization_id', 'award_id', 'supplier_id', 'id'], 'order_line_awarded');
        });
        DB::statement("ALTER TABLE work_order_lines ADD CONSTRAINT order_line_money CHECK (quantity>0 AND unit_price>=0 AND subtotal=round(quantity*unit_price,2) AND currency='ARS')");

        // Preserve existing awards/orders and materialize their original economic lines.
        DB::table('awards')->orderBy('id')->chunkById(100, function ($awards): void {
            foreach ($awards as $award) {
                $bid = DB::table('bids')->where('id', $award->bid_id)->first();
                DB::table('awards')->where('id', $award->id)->update(['total_amount' => $bid->total_amount]);
                foreach (DB::table('bid_items')->where('bid_id', $bid->id)->get() as $line) {
                    $lineId = strtolower((string) Str::ulid());
                    $snapshot = json_encode(['description' => $line->description, 'quantity' => $line->quantity, 'unit_price' => $line->unit_price, 'currency' => $bid->currency, 'legacy' => true], JSON_THROW_ON_ERROR);
                    DB::table('award_lines')->insert(['id' => $lineId, 'organization_id' => $award->organization_id, 'tender_id' => $award->tender_id, 'award_id' => $award->id, 'bid_id' => $bid->id, 'bid_item_id' => $line->id, 'tender_item_id' => $line->tender_item_id, 'supplier_id' => $bid->supplier_id, 'currency' => $bid->currency, 'quantity' => $line->quantity, 'unit_price' => $line->unit_price, 'subtotal' => $line->subtotal, 'snapshot' => $snapshot, 'created_at' => $award->awarded_at, 'updated_at' => $award->awarded_at]);
                    foreach (DB::table('work_orders')->where('award_id', $award->id)->get() as $order) {
                        DB::table('work_orders')->where('id', $order->id)->update(['supplier_id' => $bid->supplier_id, 'tender_id' => $award->tender_id]);
                        DB::table('work_order_lines')->insert(['id' => strtolower((string) Str::ulid()), 'organization_id' => $award->organization_id, 'work_order_id' => $order->id, 'award_id' => $award->id, 'supplier_id' => $bid->supplier_id, 'award_line_id' => $lineId, 'quantity' => $line->quantity, 'unit_price' => $line->unit_price, 'subtotal' => $line->subtotal, 'currency' => $bid->currency, 'snapshot' => $snapshot, 'created_at' => $order->created_at, 'updated_at' => $order->updated_at]);
                    }
                }
            }
        });
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION bidflow_award_capacity() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE requested numeric; offered numeric; allocated numeric; consumed numeric; actual_item varchar; actual_currency varchar;
BEGIN
  PERFORM 1 FROM tenders WHERE id=NEW.tender_id AND organization_id=NEW.organization_id FOR UPDATE;
  SELECT quantity INTO requested FROM tender_items WHERE id=NEW.tender_item_id AND tender_id=NEW.tender_id AND organization_id=NEW.organization_id FOR UPDATE;
  SELECT quantity,tender_item_id INTO offered,actual_item FROM bid_items WHERE id=NEW.bid_item_id AND bid_id=NEW.bid_id AND organization_id=NEW.organization_id FOR UPDATE;
  SELECT currency INTO actual_currency FROM bids WHERE id=NEW.bid_id;
  IF actual_item IS DISTINCT FROM NEW.tender_item_id OR actual_currency IS DISTINCT FROM NEW.currency THEN
    RAISE EXCEPTION 'Award line item/currency mismatch' USING ERRCODE='23514';
  END IF;
  SELECT coalesce(sum(quantity),0) INTO allocated FROM award_lines WHERE tender_item_id=NEW.tender_item_id;
  SELECT coalesce(sum(quantity),0) INTO consumed FROM award_lines WHERE bid_item_id=NEW.bid_item_id;
  IF allocated+NEW.quantity>requested OR consumed+NEW.quantity>offered THEN
    RAISE EXCEPTION 'Award quantity exceeds requested or offered quantity' USING ERRCODE='23514';
  END IF;
  RETURN NEW;
END $$;
CREATE TRIGGER award_capacity BEFORE INSERT ON award_lines FOR EACH ROW EXECUTE FUNCTION bidflow_award_capacity();
CREATE OR REPLACE FUNCTION bidflow_immutable_award_line() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN RAISE EXCEPTION 'Confirmed economic lines are immutable' USING ERRCODE='23514'; END $$;
CREATE TRIGGER award_line_immutable BEFORE UPDATE OR DELETE ON award_lines FOR EACH ROW EXECUTE FUNCTION bidflow_immutable_award_line();
CREATE TRIGGER order_line_immutable BEFORE UPDATE OR DELETE ON work_order_lines FOR EACH ROW EXECUTE FUNCTION bidflow_immutable_award_line();
CREATE OR REPLACE FUNCTION bidflow_order_matches_award() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE original record;
BEGIN
 SELECT * INTO original FROM award_lines WHERE id=NEW.award_line_id;
 IF (NEW.quantity,NEW.unit_price,NEW.subtotal,NEW.currency) IS DISTINCT FROM (original.quantity,original.unit_price,original.subtotal,original.currency) THEN
  RAISE EXCEPTION 'Order must preserve awarded economics' USING ERRCODE='23514';
 END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER order_matches_award BEFORE INSERT ON work_order_lines FOR EACH ROW EXECUTE FUNCTION bidflow_order_matches_award();
SQL);
    }

    public function down(): void
    {
        if (DB::table('awards')->where('legacy', false)->exists()) {
            throw new RuntimeException('Cannot rollback partial awards without an explicit data migration.');
        }
        DB::unprepared('DROP FUNCTION bidflow_award_capacity() CASCADE; DROP FUNCTION bidflow_immutable_award_line() CASCADE; DROP FUNCTION bidflow_order_matches_award() CASCADE;');
        Schema::dropIfExists('work_order_lines');
        Schema::dropIfExists('award_lines');
        Schema::table('work_orders', function (Blueprint $t): void {
            $t->dropForeign('order_award_identity');
            $t->dropForeign('order_supplier_link');
            $t->dropUnique('order_award_supplier');
            $t->dropUnique('order_line_identity');
        });
        DB::statement('DROP INDEX legacy_order_award');
        DB::statement('ALTER TABLE work_orders DROP CONSTRAINT order_identity_pair');
        Schema::table('work_orders', function (Blueprint $t): void {
            $t->dropColumn(['supplier_id', 'tender_id']);
            $t->unique('award_id');
        });
        DB::statement('DROP INDEX legacy_award_tender');
        DB::statement('DROP INDEX legacy_award_bid');
        DB::statement('ALTER TABLE awards DROP CONSTRAINT award_mode');
        DB::statement('ALTER TABLE awards DROP CONSTRAINT award_economics');
        Schema::table('awards', function (Blueprint $t): void {
            $t->dropForeign('award_tender_fk');
            $t->dropIndex('award_tender_fk_idx');
            $t->dropUnique('award_request_key');
            $t->dropUnique('award_tender_identity');
            $t->dropColumn(['legacy', 'request_key', 'request_hash', 'status', 'currency', 'total_amount']);
            $t->ulid('bid_id')->nullable(false)->change();
            $t->unique('bid_id');
            $t->unique('tender_id');
        });
        Schema::table('bids', function (Blueprint $t): void {
            $t->dropUnique('bids_supplier_identity');
            $t->dropColumn('submitted_snapshot');
        });
        Schema::table('bid_items', function (Blueprint $t): void {
            $t->dropUnique('bid_items_offer_identity');
            $t->dropColumn(['delivery_days', 'conditions']);
        });
        if (DB::table('tenders')->where('status', 'partially_awarded')->exists()) {
            throw new RuntimeException('Partial tender status cannot be rolled back.');
        }
        DB::statement('ALTER TABLE tenders DROP CONSTRAINT tenders_check_1');
        DB::statement("ALTER TABLE tenders ADD CONSTRAINT tenders_check_1 CHECK (status IN ('draft','published','closed','awarded','completed','cancelled','expired'))");
    }
};
