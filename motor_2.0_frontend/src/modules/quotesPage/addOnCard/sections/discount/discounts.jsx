import React from "react";
import { CardBlock } from "../../style";
import { Checkbox, CustomRadio } from "components";
import { BlockedSections } from "../../cardConfig";
import {
  CancelAll,
  SaveAddonsData,
  SetaddonsAndOthers,
} from "modules/quotesPage/quote.slice";
import UpdateButton from "../../_components/update-btn";
import { useDispatch } from "react-redux";

const Discounts = (props) => {
  // prettier-ignore
  const {
    gcvJourney, tab, register, type, selectedDiscount, volDiscount, volDiscountValue, setVolDiscountValue, bike, temp_data,
    showUpdateButtonDiscount, handleSubmit, userData, enquiry_id,
  } = props;

  const dispatch = useDispatch();

  const onSubmitDiscount = () => {
    // cancel all apis loading (quotes apis)
    dispatch(CancelAll(true));
    var data = {
      selectedDiscount: selectedDiscount,
      volDiscountValue: volDiscountValue,
    };
    dispatch(SetaddonsAndOthers(data));
    var newSelectedDiscount = [];
    if (
      selectedDiscount?.includes(
        "Is the vehicle fitted with ARAI approved anti-theft device?"
      )
    ) {
      let newD = {
        name: "anti-theft device",
      };
      newSelectedDiscount.push(newD);
    }
    if (selectedDiscount?.includes("Voluntary Discounts")) {
      let newD = {
        name: "voluntary_insurer_discounts",
        sumInsured: volDiscountValue !== "None" ? volDiscountValue : 0,
      };
      newSelectedDiscount.push(newD);
    }
    if (selectedDiscount?.includes("Vehicle Limited to Own Premises")) {
      let newD = {
        name: "Vehicle Limited to Own Premises",
      };
      newSelectedDiscount.push(newD);
    }
    if (selectedDiscount?.includes("TPPD Cover")) {
      let newD = {
        name: "TPPD Cover",
      };
      newSelectedDiscount.push(newD);
    }
    let data1 = {
      enquiryId: userData.temp_data?.enquiry_id || enquiry_id,
      addonData: { discounts: newSelectedDiscount },
    };
    dispatch(SaveAddonsData(data1));
    // resetting cancel all apis loading so quotes will restart (quotes apis)
    dispatch(CancelAll(false));
  };

  return (
    <div className={false ? "hideAddon" : "showAddon"}>
      <CardBlock>
        <>
          <div
            className={gcvJourney || tab === "tab2" ? "hideAddon" : "showAddon"}
          >
            <Checkbox
              id={"Is the vehicle fitted with ARAI approved anti-theft device?"}
              fieldName={
                "Vehicle is fitted with ARAI approved anti-theft device"
              }
              register={register}
              index={0}
              name="discount"
              tooltipData={
                "Vehicle is fitted with ARAI ARAI-approved anti-theft device - ARAI certifies vehicles meeting safety & emission standards. Insurers often offer lower premiums for such vehicles."
              }
            />
          </div>

          <div
            className={
              type === "cv" ||
              tab === "tab2" ||
              BlockedSections(import.meta.env.VITE_BROKER).includes(
                "voluntary discounts"
              )
                ? "hideAddon"
                : "showAddon"
            }
          >
            <Checkbox
              id={"Voluntary Discounts"}
              fieldName={"Voluntary Deductible"}
              register={register}
              index={1}
              name="discount"
              tooltipData={
                "This is the limit chosen by the policyholder to meet a part of the claim from his own pocket before raising it to the insurer. Choosing a higher amount of Voluntary Deductible causes a lowering in premiums through discounts."
              }
            />

            {selectedDiscount?.includes("Voluntary Discounts") && (
              <>
                {volDiscount.map((item, index) => (
                  <CustomRadio
                    id={item}
                    name="volDiscount"
                    fieldName={item}
                    index={10}
                    register={register}
                    items={volDiscount}
                    setNewChecked={setVolDiscountValue}
                    selected={volDiscountValue || volDiscount[0]}
                  />
                ))}
              </>
            )}
          </div>

          <div
            className={
              bike ||
              type === "car" ||
              temp_data?.parent?.productSubTypeCode === "MISC"
                ? "hideAddon"
                : "showAddon"
            }
          >
            <>
              <div className={bike ? "hideAddon" : "showAddon"}>
                <Checkbox
                  id={"Vehicle Limited to Own Premises"}
                  fieldName={"Vehicle Limited to Own Premises"}
                  register={register}
                  index={2}
                  name="discount"
                  tooltipData={"Covers the vehicle only when it's within the insured’s premises. No coverage for damages outside the premises."}
                />
              </div>
            </>
          </div>
          <div className={temp_data?.odOnly ? "hideAddon" : "showAddon"}>
            <Checkbox
              id={"TPPD Cover"}
              fieldName={"TPPD Cover"}
              register={register}
              index={3}
              name="discount"
              tooltipData={
                "A reduction in the premium for mandatory Third-Party Property Damage (TPPD) liability."
              }
            />
          </div>
          <>
            {showUpdateButtonDiscount && (
              <UpdateButton
                id={"updateDiscountsButton"}
                onClick={handleSubmit(onSubmitDiscount)}
              />
            )}
          </>
        </>
      </CardBlock>
    </div>
  );
};

export default Discounts;
