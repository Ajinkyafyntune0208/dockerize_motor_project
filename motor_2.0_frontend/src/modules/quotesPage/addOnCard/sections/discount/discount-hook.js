/* eslint-disable react-hooks/exhaustive-deps */
import { useEffect } from "react";
import _ from "lodash";

export const useDiscountUpdateButtonVisibility = (discountButtonProps) => {
  // prettier-ignore
  const { selectedDiscount, addOnsAndOthers, setShowUpdateButtonDiscount, volDiscountValue } = discountButtonProps
  useEffect(() => {
    if (!_.isEqual(selectedDiscount, addOnsAndOthers?.selectedDiscount))
      setShowUpdateButtonDiscount(true);
    else if (volDiscountValue !== addOnsAndOthers.volDiscountValue) {
      setShowUpdateButtonDiscount(true);
    } else {
      setShowUpdateButtonDiscount(false);
    }
  }, [
    volDiscountValue,
    addOnsAndOthers.volDiscountValue,
    selectedDiscount,
    addOnsAndOthers.selectedDiscoun,
  ]);
};
