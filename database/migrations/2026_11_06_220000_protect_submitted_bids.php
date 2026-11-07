<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE bids SET submitted_snapshot=jsonb_build_object('legacy',true,'currency',currency,'total_amount',total_amount,'conditions',conditions,'warranty',warranty,'lines',coalesce((SELECT jsonb_agg(to_jsonb(bid_items)) FROM bid_items WHERE bid_items.bid_id=bids.id),'[]'::jsonb)) WHERE status IN ('submitted','accepted','rejected')");
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION bidflow_frozen_bid() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF OLD.submitted_snapshot IS NOT NULL THEN RAISE EXCEPTION 'Submitted bid is immutable' USING ERRCODE='23514'; END IF;
 RETURN CASE WHEN TG_OP='DELETE' THEN OLD ELSE NEW END;
END $$;
CREATE TRIGGER frozen_bid BEFORE UPDATE OR DELETE ON bids FOR EACH ROW EXECUTE FUNCTION bidflow_frozen_bid();
CREATE OR REPLACE FUNCTION bidflow_bid_line() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE parent_snapshot jsonb; requested numeric;
BEGIN
 IF TG_OP='UPDATE' AND OLD.bid_id IS DISTINCT FROM NEW.bid_id THEN RAISE EXCEPTION 'Bid line cannot change parent' USING ERRCODE='23514'; END IF;
 SELECT submitted_snapshot INTO parent_snapshot FROM bids WHERE id=CASE WHEN TG_OP='DELETE' THEN OLD.bid_id ELSE NEW.bid_id END FOR UPDATE;
 IF parent_snapshot IS NOT NULL THEN RAISE EXCEPTION 'Submitted bid lines are immutable' USING ERRCODE='23514'; END IF;
 IF TG_OP<>'DELETE' THEN
  SELECT quantity INTO requested FROM tender_items WHERE id=NEW.tender_item_id;
  IF NEW.quantity>requested THEN RAISE EXCEPTION 'Offer exceeds requested quantity' USING ERRCODE='23514'; END IF;
 END IF;
 RETURN CASE WHEN TG_OP='DELETE' THEN OLD ELSE NEW END;
END $$;
CREATE TRIGGER frozen_bid_line BEFORE INSERT OR UPDATE OR DELETE ON bid_items FOR EACH ROW EXECUTE FUNCTION bidflow_bid_line();
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION bidflow_frozen_bid() CASCADE; DROP FUNCTION bidflow_bid_line() CASCADE;');
    }
};
