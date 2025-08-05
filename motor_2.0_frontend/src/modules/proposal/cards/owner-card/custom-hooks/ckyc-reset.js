import { useEffect } from "react";
import _ from "lodash";

export const useCkycUploadStateReset = ({
  temp_data,
  ckycValue,
  fields,
  setuploadFile,
  setpoa_file,
  setpoi_file,
  setForm60,
  setPoa,
  setPoi,
}) => {
  const companyAlias = temp_data?.selectedQuote?.companyAlias;
  useEffect(() => {
    if (ckycValue === "YES" && !_.isEmpty(fields)) {
      setuploadFile(false);
      setpoa_file();
      setpoi_file();
      setForm60();
      setPoa(false);
      setPoi(false);
    } else if (
      ckycValue === "NO" &&
      // (companyAlias === "bajaj_allianz" &&
      // import.meta.env.VITE_PROD === "YES") ||
      //
      // (companyAlias === "tata_aig" &&
      //   import.meta.env.VITE_PROD === "YES" &&
      //   !["OLA", "BAJAJ", "ACE", "TATA"].includes(
      //     import.meta.env.VITE_BROKER
      //   ))
      //    ||
      companyAlias === "shriram" &&
      (fields?.includes("poa") ||
        fields?.includes("poi") ||
        fields?.includes("fileupload")) &&
      !_.isEmpty(fields)
    ) {
      setuploadFile(true);
      fields?.includes("poa") && setPoa(true);
      fields?.includes("poi") && setPoi(true);
    }
  }, [ckycValue, fields]);
};
