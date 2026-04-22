-- Popup chart config for QGIS map layers (admin UI + map popups).
-- Run once on existing databases.

ALTER TABLE public.qgs_layer
  ADD COLUMN IF NOT EXISTS popup_chart_config JSONB NOT NULL DEFAULT '{}'::jsonb;
