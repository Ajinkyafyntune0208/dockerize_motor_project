/* eslint-disable react-hooks/exhaustive-deps */
import { useEffect } from "react";
import _ from "lodash";

export const useAccessoriesUpdateButtonVisibility = (accessoriesProps) => {
  // prettier-ignore
  const { 
      selectedAccesories, addOnsAndOthers, setShowUpdateButtonAccesories, ElectricAmount,
      NonElectricAmount, ExternalAmount, TrailerAmount,
    } = accessoriesProps;

  useEffect(() => {
    if (!_.isEqual(selectedAccesories, addOnsAndOthers?.selectedAccesories)) {
      setShowUpdateButtonAccesories(true);
    } else if (
      Number(ElectricAmount) !== addOnsAndOthers.vehicleElectricAccessories ||
      Number(NonElectricAmount) !==
        addOnsAndOthers.vehicleNonElectricAccessories ||
      Number(ExternalAmount) !== addOnsAndOthers.externalBiFuelKit ||
      Number(TrailerAmount) !== addOnsAndOthers.trailerCover
    ) {
      setShowUpdateButtonAccesories(true);
    } else {
      setShowUpdateButtonAccesories(false);
    }
  }, [
    selectedAccesories,
    addOnsAndOthers?.selectedAccesories,
    addOnsAndOthers.vehicleElectricAccessories,
    ElectricAmount,
    NonElectricAmount,
    ExternalAmount,
    addOnsAndOthers.vehicleNonElectricAccessories,
    addOnsAndOthers.externalBiFuelKit,
    TrailerAmount,
    addOnsAndOthers.trailerCover,
  ]);
};
