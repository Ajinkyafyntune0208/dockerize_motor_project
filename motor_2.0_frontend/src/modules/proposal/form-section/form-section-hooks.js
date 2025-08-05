import { useEffect } from "react";
import _ from 'lodash';

export const useDropout = (dropout, breakinCase, rsKycStatus, setDropout) => {
  useEffect(() => {
    if (dropout || breakinCase || rsKycStatus?.kyc_status) {
      setDropout(true);
    }
  }, [dropout, breakinCase, rsKycStatus]);
};

export const useCKYCMandate = (ckycParams, ckycStateParams) => {
  const { TempData, CardData, theme_conf } = ckycParams;
  const { owner, show, setCkycMandateModal } = ckycStateParams;
  useEffect(() => {
    if (
      !_.isEmpty(TempData) &&
      CardData?.owner?.popupPreview !== "Y" &&
      owner?.popupPreview !== "Y" &&
      !show &&
      (theme_conf?.broker_config?.ckyc_mandate ||
        import.meta.env.VITE_BROKER === "RB")
    ) {
      setCkycMandateModal(true);
    }
  }, [TempData?.userProposal?.additionalData]);
};
