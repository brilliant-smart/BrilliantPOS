import { useState, useEffect } from "react";
import { getSettings, updateSettings, type Settings } from "@/app/api/settings";
import { useToast } from "@/hooks/use-toast";

export default function Settings() {
  const { toast } = useToast();
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
    receipt_footer: "",
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
          receipt_footer: data.receipt?.receipt_footer || "",
        });
      }
    } catch (error) {
      toast({ title: "Error", description: "Failed to load settings", variant: "destructive" });
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
        { key: "receipt_footer", value: formData.receipt_footer },
      ];
      await updateSettings(settingsToUpdate);
      toast({ title: "Success", description: "Settings saved successfully" });
    } catch (error) {
      toast({ title: "Error", description: "Failed to save settings", variant: "destructive" });
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
        <p className="text-sm text-muted-foreground">
          These details appear on receipts, reports, and other business documents.
        </p>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium mb-1">Store Name</label>
            <input
              type="text"
              value={formData.store_name}
              onChange={(e) => setFormData({ ...formData, store_name: e.target.value })}
              className="w-full border rounded-md px-3 py-2 text-sm"
              placeholder="e.g., My Store"
            />
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Store Tagline</label>
            <input
              type="text"
              value={formData.store_tagline}
              onChange={(e) => setFormData({ ...formData, store_tagline: e.target.value })}
              className="w-full border rounded-md px-3 py-2 text-sm"
              placeholder="e.g., Smart Shopping, Better Living"
            />
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Phone</label>
            <input
              type="text"
              value={formData.store_phone}
              onChange={(e) => setFormData({ ...formData, store_phone: e.target.value })}
              className="w-full border rounded-md px-3 py-2 text-sm"
              placeholder="+234 xxx xxx xxxx"
            />
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Email</label>
            <input
              type="email"
              value={formData.store_email}
              onChange={(e) => setFormData({ ...formData, store_email: e.target.value })}
              className="w-full border rounded-md px-3 py-2 text-sm"
              placeholder="store@example.com"
            />
          </div>
          <div className="md:col-span-2">
            <label className="block text-sm font-medium mb-1">Address</label>
            <input
              type="text"
              value={formData.store_address}
              onChange={(e) => setFormData({ ...formData, store_address: e.target.value })}
              className="w-full border rounded-md px-3 py-2 text-sm"
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
            <label className="block text-sm font-medium mb-1">Currency Symbol</label>
            <input
              type="text"
              value={formData.currency_symbol}
              onChange={(e) => setFormData({ ...formData, currency_symbol: e.target.value })}
              className="w-full border rounded-md px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">VAT Rate (%)</label>
            <input
              type="number"
              step="0.1"
              value={formData.vat_rate}
              onChange={(e) => setFormData({ ...formData, vat_rate: parseFloat(e.target.value) || 0 })}
              className="w-full border rounded-md px-3 py-2 text-sm"
            />
          </div>
          <div className="md:col-span-2">
            <label className="block text-sm font-medium mb-1">Receipt Footer Message</label>
            <input
              type="text"
              value={formData.receipt_footer}
              onChange={(e) => setFormData({ ...formData, receipt_footer: e.target.value })}
              className="w-full border rounded-md px-3 py-2 text-sm"
              placeholder="Thank you for your purchase!"
            />
          </div>
        </div>
      </div>

      <div className="flex justify-end">
        <button
          onClick={handleSave}
          disabled={saving}
          className="px-6 py-2 bg-primary text-white rounded-md hover:bg-primary/90 disabled:opacity-50"
        >
          {saving ? "Saving..." : "Save Settings"}
        </button>
      </div>
    </div>
  );
}