import { useEffect, useRef, useState } from 'react';
import { Html5Qrcode } from 'html5-qrcode';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Camera, X, Scan } from 'lucide-react';
import { toast } from 'sonner';

interface BarcodeScannerProps {
  onScan: (barcode: string) => void;
  onClose?: () => void;
  title?: string;
  showManualInput?: boolean;
}

export default function BarcodeScanner({ 
  onScan, 
  onClose, 
  title = "Scan Barcode",
  showManualInput = true 
}: BarcodeScannerProps) {
  const [isScanning, setIsScanning] = useState(false);
  const [manualBarcode, setManualBarcode] = useState('');
  const scannerRef = useRef<Html5Qrcode | null>(null);
  const scannerElementId = 'barcode-scanner-container';

  const startScanner = async () => {
    try {
      if (!scannerRef.current) {
        scannerRef.current = new Html5Qrcode(scannerElementId);
      }

      const config = {
        fps: 10,
        qrbox: { width: 250, height: 150 },
        formatsToSupport: [
          // Common barcode formats
          0,  // CODE_128
          1,  // CODE_39
          2,  // CODE_93
          3,  // CODABAR
          7,  // EAN_13
          8,  // EAN_8
          9,  // ITF
          10, // UPC_A
          11, // UPC_E
        ],
      };

      await scannerRef.current.start(
        { facingMode: "environment" }, // Use back camera
        config,
        (decodedText) => {
          // Success callback
          toast.success(`Barcode detected: ${decodedText}`);
          onScan(decodedText);
          stopScanner();
        },
        (errorMessage) => {
          // Error callback (can be ignored for continuous scanning)
          // console.log('Scanning...', errorMessage);
        }
      );

      setIsScanning(true);
    } catch (err: any) {
      console.error('Scanner error:', err);
      toast.error(err.message || 'Failed to start camera');
    }
  };

  const stopScanner = async () => {
    try {
      if (scannerRef.current && isScanning) {
        await scannerRef.current.stop();
        setIsScanning(false);
      }
    } catch (err) {
      console.error('Error stopping scanner:', err);
    }
  };

  const handleManualSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (manualBarcode.trim()) {
      onScan(manualBarcode.trim());
      setManualBarcode('');
    }
  };

  useEffect(() => {
    return () => {
      // Cleanup on unmount
      if (scannerRef.current) {
        scannerRef.current.stop().catch(console.error);
      }
    };
  }, []);

  return (
    <Card className="w-full max-w-md mx-auto">
      <CardHeader className="flex flex-row items-center justify-between">
        <CardTitle className="flex items-center gap-2">
          <Scan className="h-5 w-5" />
          {title}
        </CardTitle>
        {onClose && (
          <Button variant="ghost" size="icon" onClick={onClose}>
            <X className="h-4 w-4" />
          </Button>
        )}
      </CardHeader>
      <CardContent className="space-y-4">
        {/* Camera Scanner */}
        <div className="space-y-2">
          <div 
            id={scannerElementId} 
            className={`w-full ${isScanning ? 'block' : 'hidden'} rounded-lg overflow-hidden border-2 border-primary`}
          />
          
          {!isScanning ? (
            <Button 
              onClick={startScanner} 
              className="w-full"
              variant="default"
            >
              <Camera className="mr-2 h-4 w-4" />
              Start Camera Scanner
            </Button>
          ) : (
            <Button 
              onClick={stopScanner} 
              className="w-full"
              variant="destructive"
            >
              <X className="mr-2 h-4 w-4" />
              Stop Scanner
            </Button>
          )}
        </div>

        {/* Manual Input Option */}
        {showManualInput && (
          <div className="space-y-2">
            <div className="relative">
              <div className="absolute inset-0 flex items-center">
                <span className="w-full border-t" />
              </div>
              <div className="relative flex justify-center text-xs uppercase">
                <span className="bg-background px-2 text-muted-foreground dark:text-muted-foreground/80">
                  Or enter manually
                </span>
              </div>
            </div>
            
            <form onSubmit={handleManualSubmit} className="flex gap-2">
              <input
                type="text"
                placeholder="Enter barcode number..."
                value={manualBarcode}
                onChange={(e) => setManualBarcode(e.target.value)}
                className="flex-1 px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
              />
              <Button type="submit" disabled={!manualBarcode.trim()}>
                Add
              </Button>
            </form>
          </div>
        )}

        <p className="text-xs text-muted-foreground dark:text-muted-foreground/80 text-center">
          Position the barcode within the camera frame or enter the code manually
        </p>
      </CardContent>
    </Card>
  );
}
