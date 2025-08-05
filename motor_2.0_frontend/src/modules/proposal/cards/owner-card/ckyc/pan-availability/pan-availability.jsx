import { useEffect, useMemo } from "react";
import { Col, ToggleButton } from "react-bootstrap";
import { FormGroupTag, ButtonGroupTag } from "modules/proposal/style";
import _ from "lodash";

export const PanAvailability = ({
  temp_data,
  panAvailability,
  setPanAvailability,
  setpan_file,
  register,
  setValue,
  owner,
  CardData,
  watch,
  ckycValue,
  setuploadFile,
}) => {
  let companyAlias = temp_data?.selectedQuote?.companyAlias;

  let isInputApplicable = [
    "ACE",
    import.meta.env.VITE_PROD === "NO" && "HEROCARE",
    "BAJAJ",
  ].includes(import.meta.env.VITE_BROKER)
    ? ["royal_sundaram", "bajaj_allianz"]
    : ["royal_sundaram", "bajaj_allianz"];

  let prefillForBrokers =
    isInputApplicable.includes(companyAlias) ||
    (!["RB", "ABIBL"].includes(import.meta.env.VITE_BROKER) &&
      temp_data?.selectedQuote?.companyAlias === "shriram");

  let isIndividual =
    temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "I";

  //prefill field
  useEffect(() => {
    if (_.isEmpty(owner) && !_.isEmpty(CardData?.owner)) {
      let isPanAvailableInTemp = CardData?.owner?.isPanPresent;

      prefillForBrokers &&
        isIndividual &&
        setPanAvailability(isPanAvailableInTemp);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [CardData.owner]);

  //Memonize PAN
  const isPanPresent = watch("isPanPresent");
  useMemo(() => {
    if (isPanPresent !== panAvailability && isPanPresent) {
      setPanAvailability(isPanPresent);
    } else if (
      temp_data?.selectedQuote?.companyAlias === "bajaj_allianz" &&
      temp_data?.quoteLog?.finalPremiumAmount * 1 < 50000
    ) {
      isPanPresent === "YES" ? setuploadFile(false) : setuploadFile(true);
    }
  }, [isPanPresent]);

  //reset pan availability to yes if ckyc number availability is changed.
  useMemo(() => {
    if (isInputApplicable && ckycValue === "YES") {
      setPanAvailability("YES");
      setuploadFile(false);
    }
  }, [ckycValue]);

  //as per the bajaj new flow hidding the pan availability field when premium is greater than 50000
  const panhidden =
    temp_data?.selectedQuote?.companyAlias === "bajaj_allianz" &&
    temp_data?.quoteLog?.finalPremiumAmount * 1 > 50000;

  return (
    <>
      {prefillForBrokers && isIndividual && !panhidden && (
        <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
          <FormGroupTag style={{ paddingTop: "10px" }} mandatory>
            Do you have PAN Number?
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
                  name="panPresent"
                  value={radio}
                  checked={panAvailability === radio}
                  onInput={() => setValue("isPanPresent", radio)}
                  onChange={(e) => {
                    setPanAvailability(e.target.value);
                    if (e.target.value === "NO") {
                      setpan_file("");
                    }
                  }}
                >
                  {_.capitalize(radio)}
                </ToggleButton>
              ))}
            </ButtonGroupTag>
          </div>
        </Col>
      )}
      <input
        type="hidden"
        name="isPanPresent"
        value={panAvailability}
        ref={register}
      />
    </>
  );
};
