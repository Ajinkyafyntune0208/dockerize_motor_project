import { useEffect } from "react";

export const useShowToastMessage = (
  callToaster,
  notify,
  setCall,
  setToasterShown
) => {
  useEffect(() => {
    if (callToaster) {
      notify();
      setCall(false);
      setToasterShown && setToasterShown(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [callToaster]);
};
