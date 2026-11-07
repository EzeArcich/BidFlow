<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION bidflow_frozen_award() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF NOT OLD.legacy THEN RAISE EXCEPTION 'Confirmed award is immutable' USING ERRCODE='23514'; END IF;
 RETURN CASE WHEN TG_OP='DELETE' THEN OLD ELSE NEW END;
END $$;
CREATE TRIGGER frozen_award BEFORE UPDATE OR DELETE ON awards FOR EACH ROW EXECUTE FUNCTION bidflow_frozen_award();
CREATE OR REPLACE FUNCTION bidflow_order_identity() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF (NEW.organization_id,NEW.award_id,NEW.supplier_id,NEW.tender_id,NEW.number) IS DISTINCT FROM (OLD.organization_id,OLD.award_id,OLD.supplier_id,OLD.tender_id,OLD.number) THEN
  RAISE EXCEPTION 'Issued order identity is immutable' USING ERRCODE='23514';
 END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER frozen_order_identity BEFORE UPDATE ON work_orders FOR EACH ROW EXECUTE FUNCTION bidflow_order_identity();
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION bidflow_frozen_award() CASCADE; DROP FUNCTION bidflow_order_identity() CASCADE;');
    }
};
