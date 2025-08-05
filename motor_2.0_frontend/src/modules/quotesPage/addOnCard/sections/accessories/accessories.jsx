import React from "react";
import { Checkbox, ErrorMsg } from "components";
import { CardBlock, InputFieldSmall } from "../../style";
import { Form } from "react-bootstrap";
import UpdateButton from "../../_components/update-btn";
import { errorAlert } from "../../cardConfig";
import {
  CancelAll,
  SaveAddonsData,
  SetaddonsAndOthers,
} from "modules/quotesPage/quote.slice";
import { useDispatch } from "react-redux";
import {
  getAccessoriesData,
  getNewSelectedAccessories,
  isAccessoriesEmpty,
} from "../addons";

const Accessories = (props) => {
  // prettier-ignore
  const {
    tab, register, selectedAccesories, amountElectricPrefill, numOnlyNoZero, amountNonElectricPrefill, 
    errors, bike, temp_data, amountCngPrefill, gcvJourney, amountTrailerPrefill, showUpdateButtonAccesories, 
    handleSubmit, accesories, ElectricAmount, NonElectricAmount, ExternalAmount, TrailerAmount, userData, enquiry_id,
  } = props

  const dispatch = useDispatch();

  const onSubmitAccesories = () => {
    if (
      // prettier-ignore
      isAccessoriesEmpty(accesories, ElectricAmount, NonElectricAmount, ExternalAmount)
    ) {
      errorAlert();
    } else {
      // cancel all apis loading (quotes apis)
      dispatch(CancelAll(true));

      // prettier-ignore
      const accessoriesProps = { selectedAccesories, temp_data, ExternalAmount, NonElectricAmount, ElectricAmount, TrailerAmount }
      var data = getAccessoriesData(accessoriesProps);
      dispatch(SetaddonsAndOthers(data));

      // prettier-ignore
      const newSelectedAccesories =
        getNewSelectedAccessories(accessoriesProps);

      const data1 = {
        enquiryId: userData.temp_data?.enquiry_id || enquiry_id,
        addonData: { accessories: newSelectedAccesories },
      };
      dispatch(SaveAddonsData(data1));
      // resetting cancel all apis loading so quotes will restart (quotes apis)
      dispatch(CancelAll(false));
    }
  };

  return (
    <CardBlock>
      <div className={tab === "tab2" ? "hideAddon" : "showAddon"}>
        <Checkbox
          id={"Electrical Accessories"}
          fieldName={"Electrical Accessories"}
          register={register}
          index={0}
          name="accesories"
          tooltipData={
            "Electrical accessories include music system, air conditioners, different sort of lights such as brake or fog light, etc."
          }
        />
        {selectedAccesories?.includes("Electrical Accessories") && (
          <>
            <InputFieldSmall>
              <Form.Control
                type="text"
                placeholder="Enter value "
                name="amountElectric"
                defaultValue={amountElectricPrefill}
                minlength="2"
                ref={register}
                onInput={(e) =>
                  (e.target.value =
                    e.target.value.length <= 1
                      ? "" + e.target.value.replace(/[^0-9]/gi, "")
                      : e.target.value)
                }
                size="sm"
                maxLength="6"
                onKeyDown={(e) => {
                  numOnlyNoZero(e);
                }}
              />
            </InputFieldSmall>
          </>
        )}
        <div className={false ? "hideAddon" : "showAddon"}>
          <Checkbox
            id={"Non-Electrical Accessories"}
            fieldName={"Non-Electrical Accessories"}
            register={register}
            index={1}
            name="accesories"
            tooltipData={
              "Non-electrical accessories include the interior fitting in the car, seat covers, alloy wheels etc"
            }
          />
          {selectedAccesories?.includes("Non-Electrical Accessories") && (
            <>
              <InputFieldSmall>
                <Form.Control
                  type="text"
                  placeholder="Enter value "
                  defaultValue={amountNonElectricPrefill}
                  name="amountNonElectric"
                  minlength="2"
                  ref={register}
                  onInput={(e) =>
                    (e.target.value =
                      e.target.value.length <= 1
                        ? "" + e.target.value.replace(/[^0-9]/gi, "")
                        : e.target.value)
                  }
                  errors={errors.amountNonElectric}
                  size="sm"
                  maxLength="6"
                  onKeyDown={(e) => {
                    numOnlyNoZero(e);
                  }}
                />
              </InputFieldSmall>
            </>
          )}
        </div>
      </div>
      {!["CNG", "LPG", "ELECTRIC", "DIESEL"].includes(temp_data?.fuel) &&
        true && (
          <>
            <div className={bike ? "hideAddon" : "showAddon"}>
              <Checkbox
                id={"External Bi-Fuel Kit CNG/LPG"}
                fieldName={"External Bi-Fuel Kit CNG/LPG"}
                register={register}
                index={2}
                name="accesories"
                tooltipData={
                  "It covers the damages to the externally fitted fuel kit such as CNG/LPG."
                }
              />
              {selectedAccesories?.includes("External Bi-Fuel Kit CNG/LPG") && (
                <>
                  <InputFieldSmall>
                    <Form.Control
                      type="text"
                      placeholder="Enter value "
                      name="amountLpg"
                      defaultValue={amountCngPrefill}
                      minlength="2"
                      ref={register}
                      onInput={(e) =>
                        (e.target.value =
                          e.target.value.length <= 1
                            ? "" + e.target.value.replace(/[^0-9]/gi, "")
                            : e.target.value)
                      }
                      errors={errors.amountLpg}
                      size="sm"
                      maxLength="6"
                      onKeyDown={(e) => {
                        numOnlyNoZero(e);
                      }}
                    />
                  </InputFieldSmall>
                </>
              )}
            </div>
          </>
        )}
      {gcvJourney && import.meta.env.VITE_BROKER === "FYNTUNE" && (
        <div>
          <Checkbox
            id={"Trailer"}
            fieldName={"Trailer"}
            register={register}
            index={3}
            name="accesories"
            tooltipData={"Trailer"}
          />
          {selectedAccesories?.includes("Trailer") && (
            <>
              <InputFieldSmall>
                <Form.Control
                  type="text"
                  placeholder="Enter value "
                  name="amountTrailer"
                  defaultValue={amountTrailerPrefill}
                  minlength="2"
                  ref={register}
                  onInput={(e) =>
                    (e.target.value =
                      e.target.value.length <= 1
                        ? "" + e.target.value.replace(/[^0-9]/gi, "")
                        : e.target.value)
                  }
                  errors={errors.amountTrailer}
                  size="sm"
                  maxLength="6"
                  onKeyDown={(e) => {
                    numOnlyNoZero(e);
                  }}
                />
                {!!errors.amountTrailer && (
                  <ErrorMsg fontSize={"12px"}>
                    {errors.amountTrailer.message}
                  </ErrorMsg>
                )}
              </InputFieldSmall>
            </>
          )}
        </div>
      )}
      {showUpdateButtonAccesories && (
        <UpdateButton
          id={"updateAccesoriesButton"}
          onClick={handleSubmit(onSubmitAccesories)}
        />
      )}
    </CardBlock>
  );
};

export default Accessories;
