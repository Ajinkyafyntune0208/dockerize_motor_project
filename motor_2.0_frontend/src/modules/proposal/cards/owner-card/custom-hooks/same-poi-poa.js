import { useEffect } from "react";

export const useSamePOIAndPOA = ({
  poiIdentity,
  poaIdentity,
  selectedpoiIdentity,
  selectedpoaIdentity,
  setPoa_disabled,
  setPoi_disabled,
  poi_identity,
  poa_identity,
  setValue,
  watch,
}) => {
  var poa_number = watch(`poa_${poa_identity}`);
  var poi_number = watch(`poi_${poi_identity}`);
  useEffect(() => {
    if (
      poiIdentity === poaIdentity &&
      ((selectedpoiIdentity &&
        poi_number &&
        poi_number?.length === selectedpoiIdentity?.length) ||
        (selectedpoaIdentity &&
          poa_number &&
          poa_number?.length === selectedpoaIdentity?.length))
    ) {
      if (poi_number?.length === selectedpoiIdentity?.length) {
        setPoa_disabled(true);
        setPoi_disabled(false);
        setValue(`poa_${poa_identity}`, poi_number);
      } else if (poa_number?.length === selectedpoaIdentity?.length) {
        setPoa_disabled(false);
        setPoi_disabled(false);
        // setValue(`poi_${poi_identity}`, poa_number);
      } else {
        setPoa_disabled(false);
        setPoi_disabled(false);
        setValue(`poi_${poi_identity}`, "");
        setValue(`poa_${poa_identity}`, "");
      }
    } else {
      setPoa_disabled(false);
      setPoi_disabled(false);
    }
  }, [poiIdentity, poaIdentity, poi_number, poa_number]);
};
