/* eslint-disable react-hooks/exhaustive-deps */
import { useEffect } from "react";
import _ from "lodash";

export const useAdditionalUpdateButtonVisibility = (accessoriesButtonProps) => {
  // prettier-ignore
  const {
      selectedAdditions, addOnsAndOthers, temp_data, setShowUpdateButtonAdditions, unNamedCoverValue,
      additionalPaidDriver, LLNumberDriver, LLNumberConductor, LLNumberCleaner, paPaidDriverGCV,
      countries, selectedLLpaidItmes, nfppCurrentValue
    } = accessoriesButtonProps;

  useEffect(() => {
    if (
      !_.isEqual(selectedAdditions, addOnsAndOthers?.selectedAdditions) &&
      !temp_data?.odOnly
    ) {
      _.isEmpty(addOnsAndOthers?.selectedAdditions)
        ? !_.isEqual(
            selectedAdditions?.flat(1),
            addOnsAndOthers?.selectedAdditions
          ) &&
          !temp_data?.odOnly &&
          setShowUpdateButtonAdditions(true)
        : setShowUpdateButtonAdditions(true);
    } else if (
      //covers which are not available in saod
      ((unNamedCoverValue !== addOnsAndOthers?.unNamedCoverValue ||
        additionalPaidDriver !== addOnsAndOthers?.additionalPaidDriver ||
        Number(LLNumberDriver) !== Number(addOnsAndOthers?.LLNumberDriver) ||
        Number(LLNumberConductor) !==
          Number(addOnsAndOthers?.LLNumberConductor) ||
        Number(LLNumberCleaner) !== Number(addOnsAndOthers?.LLNumberCleaner) ||
        Number(nfppCurrentValue) !== Number(addOnsAndOthers?.nfppValue) ||
        paPaidDriverGCV !== addOnsAndOthers?.paPaidDriverGCV) &&
        !temp_data?.odOnly) ||
      // covers which are available in saod
      !_.isEqual(
        addOnsAndOthers?.countries ? addOnsAndOthers?.countries : [],
        countries
      )
    ) {
      setShowUpdateButtonAdditions(true);
    } else if (
      !_.isEqual(selectedLLpaidItmes, addOnsAndOthers?.LLpaidItmes) &&
      !temp_data?.odOnly
    ) {
      setShowUpdateButtonAdditions(true);
    } else {
      setShowUpdateButtonAdditions(false);
    }
  }, [
    selectedAdditions,
    addOnsAndOthers?.selectedAdditions,
    unNamedCoverValue,
    additionalPaidDriver,
    addOnsAndOthers?.additionalPaidDriver,
    addOnsAndOthers?.unNamedCoverValue,
    LLNumberDriver,
    LLNumberConductor,
    LLNumberCleaner,
    addOnsAndOthers?.paPaidDriverGCV,
    addOnsAndOthers?.LLNumberCleaner,
    addOnsAndOthers?.LLNumberConductor,
    addOnsAndOthers?.LLNumberDriver,
    selectedLLpaidItmes,
    addOnsAndOthers?.LLpaidItmes,
    addOnsAndOthers?.nfppValue,
    countries,
  ]);
};
