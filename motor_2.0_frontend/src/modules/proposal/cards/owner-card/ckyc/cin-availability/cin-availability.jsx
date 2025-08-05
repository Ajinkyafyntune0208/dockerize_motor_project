import { useEffect } from "react";
import { Col, ToggleButton } from "react-bootstrap";
import { FormGroupTag, ButtonGroupTag } from "modules/proposal/style";
import _ from "lodash";

export const CinAvailability = ({
  temp_data,
  fields,
  register,
  setValue,
  cinAvailability,
  setCinAvailability,
  resubmit,
  uploadFile,
  ckycValue,
  owner,
  CardData
}) => {
  const enableField = fields && fields?.includes("ckyc");
  const companyAlias = temp_data?.selectedQuote?.companyAlias;

  const isCinApplicable =
    // false &&
    enableField &&
    ckycValue === "NO" &&
    temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "C" &&
    companyAlias === "tata_aig" 
    &&
    uploadFile
    ;
  
    useEffect(() => {
        if (_.isEmpty(owner) && !_.isEmpty(CardData?.owner)) {
          temp_data?.selectedQuote?.companyAlias === "tata_aig" &&
            temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "C" &&
            setCinAvailability(CardData?.owner?.isCinPresent);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
      }, [CardData.owner]);   

  return (
    <>
      {isCinApplicable && (
        <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
          <FormGroupTag style={{ paddingTop: "10px" }} mandatory>
            Do you have CIN Number?
          </FormGroupTag>
          <div className="" style={{ width: "100%", paddingTop: "2px" }}>
            <ButtonGroupTag toggle style={{ width: "100%" }}>
              {["YES", "NO"].map((radio, idx) => (
                <ToggleButton
                  style={{
                    width: "100%",
                    minHeight: "32px",
                  }}
                  key={idx}
                  className={`${idx === 0 ? "mr-4" : "mr-0"} "mb-2"`}
                  type="radio"
                  variant="secondary"
                  ref={register}
                  size="sm"
                  tabIndex={"0"}
                  name="cinAvailable"
                  value={radio}
                  checked={cinAvailability === radio}
                  onInput={() => [
                    setValue("isCinPresent", radio),
                    radio === "YES"
                      ? setValue("poi_identity", "cinNumber")
                      : setValue("poi_identity", ""),
                  ]}
                  // readOnly={["universal_sompo", "royal_sundaram"].includes(companyAlias)}
                  onChange={(e) => {
                    !resubmit && setCinAvailability(e.target.value);
                  }}
                >
                  {_.capitalize(radio)}
                </ToggleButton>
              ))}
            </ButtonGroupTag>
          </div>
          <input
            type="hidden"
            name="isCinPresent"
            value={cinAvailability}
            ref={register}
          />
        </Col>
      )}
    </>
  );
};
