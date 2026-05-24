import { useState, useEffect } from "react";
import { getSettings, updateSettings, type Settings } from "@/app/api/settings";
import { toast } from "sonner";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";

export default function Settings() {
  const [settings, setSettings] = useState<Settings | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const [formData, setFormData] = useState({
    store_name: "",
    store_phone: "",
    store_email: "",
    store_address: "",
    store_tagline: "",
    currency_symbol: "",
    vat_rate: 0,
    credit_overdue_threshold_days: 7,
    receipt_footer: "",
    receipt_prompt_enabled: true,
  });

  useEffect(() => {
    loadSettings();
  }, []);

  const loadSettings = async () => {
    try {
      setLoading(true);
      const data = await getSettings();
      setSettings(data);
      if (data.general) {
        setFormData({
          store_name: data.general.store_name || "",
          store_phone: data.general.store_phone || "",
          store_email: data.general.store_email || "",
          store_address: data.general.store_address || "",
          store_tagline: data.general.store_tagline || "",
          currency_symbol: data.general.currency_symbol || "₦",
          vat_rate: data.general.vat_rate ?? 0,
          credit_overdue_threshold_days: data.general.credit_overdue_threshold_days ?? 7,
          receipt_prompt_enabled: data.general.receipt_prompt_enabled ?? true,
          receipt_footer: data.general.receipt_footer ?? data.receipt?.receipt_footer ?? "",
        });
        localStorage.setItem('brilliant_pos_receipt_prompt', (data.general.receipt_prompt_enabled ?? true) ? '1' : '0');
      }
    } catch (error) {
      toast.error("Failed to load settings");
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async () => {
    try {
      setSaving(true);
      const settingsToUpdate = [
        { key: "store_name", value: formData.store_name },
        { key: "store_phone", value: formData.store_phone },
        { key: "store_email", value: formData.store_email },
        { key: "store_address", value: formData.store_address },
        { key: "store_tagline", value: formData.store_tagline },
        { key: "currency_symbol", value: formData.currency_symbol },
        { key: "vat_rate", value: String(formData.vat_rate) },
        { key: "credit_overdue_threshold_days", value: String(formData.credit_overdue_threshold_days) },
        { key: "receipt_prompt_enabled", value: formData.receipt_prompt_enabled ? "1" : "0" },
        { key: "receipt_footer", value: formData.receipt_footer },
      ];
      await updateSettings(settingsToUpdate);
      localStorage.setItem('brilliant_pos_receipt_prompt', formData.receipt_prompt_enabled ? '1' : '0');
      toast.success("Settings saved successfully");
    } catch (error) {
      toast.error("Failed to save settings");
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
      </div>
    );
  }

  return (
    <div className="space-y-6 p-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">Settings</h1>
      </div>

      {/* Store Information */}
      <div className="bg-card rounded-lg border p-6 space-y-4">
        <h2 className="text-lg font-semibold">Store Information</h2>
        <p className="text-sm text-muted-foreground dark:text-muted-foreground/80">
          These details appear on receipts, reports, and other business documents.
        </p>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <Label htmlFor="store_name">Store Name</Label>
            <Input
              id="store_name"
              type="text"
              value={formData.store_name}
              onChange={(e) => setFormData({ ...formData, store_name: e.target.value })}
              placeholder="e.g., My Store"
            />
          </div>
          <div>
            <Label htmlFor="store_tagline">Store Tagline</Label>
            <Input
              id="store_tagline"
              type="text"
              value={formData.store_tagline}
              onChange={(e) => setFormData({ ...formData, store_tagline: e.target.value })}
              placeholder="e.g., Smart Shopping, Better Living"
            />
          </div>
          <div>
            <Label htmlFor="store_phone">Phone</Label>
            <Input
              id="store_phone"
              type="text"
              value={formData.store_phone}
              onChange={(e) => setFormData({ ...formData, store_phone: e.target.value })}
              placeholder="+234 xxx xxx xxxx"
            />
          </div>
          <div>
            <Label htmlFor="store_email">Email</Label>
            <Input
              id="store_email"
              type="email"
              value={formData.store_email}
              onChange={(e) => setFormData({ ...formData, store_email: e.target.value })}
              placeholder="store@example.com"
            />
          </div>
          <div className="md:col-span-2">
            <Label htmlFor="store_address">Address</Label>
            <Input
              id="store_address"
              type="text"
              value={formData.store_address}
              onChange={(e) => setFormData({ ...formData, store_address: e.target.value })}
              placeholder="123 Main Street, City, State"
            />
          </div>
        </div>
      </div>

      {/* Receipt Settings */}
      <div className="bg-card rounded-lg border p-6 space-y-4">
        <h2 className="text-lg font-semibold">Receipt & Regional Settings</h2>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <Label htmlFor="currency_symbol">Currency Symbol</Label>
            <Input
              id="currency_symbol"
              type="text"
              value={formData.currency_symbol}
              onChange={(e) => setFormData({ ...formData, currency_symbol: e.target.value })}
            />
          </div>
          <div>
            <Label htmlFor="vat_rate">VAT Rate (%)</Label>
            <Input
              id="vat_rate"
              type="number"
              step="0.1"
              value={formData.vat_rate}
              onChange={(e) => setFormData({ ...formData, vat_rate: parseFloat(e.target.value) || 0 })}
            />
          </div>
          <div>
            <Label htmlFor="receipt_footer">Receipt Footer Message</Label>
            <Input
              id="receipt_footer"
              type="text"
              value={formData.receipt_footer}
              onChange={(e) => setFormData({ ...formData, receipt_footer: e.target.value })}
              placeholder="Thank you for your purchase!"
            />
          </div>
          <div className="flex items-center justify-between rounded-lg border p-4">
            <div className="space-y-0.5">
              <Label className="text-base">Print Receipts</Label>
              <p className="text-sm text-muted-foreground">Show receipt dialog after each sale</p>
            </div>
            <Switch
              checked={formData.receipt_prompt_enabled}
              onCheckedChange={(checked) => setFormData({ ...formData, receipt_prompt_enabled: checked })}
            />
          </div>
        </div>
      </div>

      {/* Credit Settings */}
      <div className="bg-card rounded-lg border p-6 space-y-4">
        <h2 className="text-lg font-semibold">Credit Settings</h2>
        <p className="text-sm text-muted-foreground dark:text-muted-foreground/80">
          Configure how credit sales and overdue payments are managed.
        </p>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <Label htmlFor="credit_overdue_threshold_days">Overdue Threshold (Days)</Label>
            <Input
              id="credit_overdue_threshold_days"
              type="number"
              min="1"
              value={formData.credit_overdue_threshold_days}
              onChange={(e) => setFormData({ ...formData, credit_overdue_threshold_days: parseInt(e.target.value) || 7 })}
            />
            <p className="text-sm text-muted-foreground mt-1">Sales unpaid after this many days are considered overdue</p>
          </div>
        </div>
      </div>

      <div className="flex justify-end">
        <Button
          onClick={handleSave}
          disabled={saving}
        >
          {saving ? "Saving..." : "Save Settings"}
        </Button>
      </div>
    </div>
  );
}