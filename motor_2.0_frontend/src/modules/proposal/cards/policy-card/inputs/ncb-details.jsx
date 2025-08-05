import React from "react";

const NcbInputs = ({ register, temp_data }) => {
  return (
    <>
      <input
        type="hidden"
        name="isClaim"
        ref={register}
        value={temp_data?.corporateVehiclesQuoteRequest?.isClaim}
      />
      <input
        type="hidden"
        name="previousNcb"
        ref={register}
        value={temp_data?.corporateVehiclesQuoteRequest?.previousNcb}
      />
      <input
        type="hidden"
        name="applicableNcb"
        ref={register}
        value={temp_data?.corporateVehiclesQuoteRequest?.applicableNcb}
      />
    </>
  );
};

export default NcbInputs;
