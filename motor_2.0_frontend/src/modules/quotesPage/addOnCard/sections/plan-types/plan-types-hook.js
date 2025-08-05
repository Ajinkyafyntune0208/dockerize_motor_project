/* eslint-disable react-hooks/exhaustive-deps */
import { useEffect } from "react";
import _ from "lodash";

// prettier-ignore
export const useGetShortTerm3FlagAce = (temp_data, setShortCompPolicy3, setAnnualCompPolicy, setShortCompPolicy6, sh3Enable) => {
    useEffect(() => {
      if (
        !_.isEmpty(temp_data) &&
        temp_data?.journeyCategory === "PCV" &&
        temp_data?.journeySubCategory === "TAXI" &&
        import.meta.env.VITE_BROKER === "ACE"
      ) {
        setShortCompPolicy3(true);
        setAnnualCompPolicy(false);
        setShortCompPolicy6(false);
      }
    }, [sh3Enable]);
  };

export const useGetShortTerm3Flag = (shortCompPolicy3, setShortTerm3) => {
  useEffect(() => {
    if (shortCompPolicy3) {
      setShortTerm3(true);
    } else {
      setShortTerm3(false);
    }
  }, [shortCompPolicy3]);
};

export const useGetShortTerm6Flag = (shortCompPolicy6, setShortTerm6) => {
  useEffect(() => {
    if (shortCompPolicy6) {
      setShortTerm6(true);
    } else {
      setShortTerm6(false);
    }
  }, [shortCompPolicy6]);
};
