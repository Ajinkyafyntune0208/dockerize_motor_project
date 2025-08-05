import { useEffect } from "react";

export default function useUnloadBeacon({ url, payload = () => {} }) {
  const eventHandler = () => navigator.sendBeacon(url, payload());

  useEffect(() => {
    if (import.meta.env.VITE_BROKER === "ABIBL") {
      window.addEventListener("beforeunload", eventHandler, true);
      return () => {
        window.removeEventListener("beforeunload", eventHandler, true);
      };
    }
  }, []);
}
